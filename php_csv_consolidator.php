<?php
/**
 * AI-Powered CSV Knowledge Base Consolidator
 * 
 * This script consolidates multiple CSV files by:
 * 1. Finding duplicate and near-duplicate entries
 * 2. Using AI APIs to intelligently merge similar records
 * 3. Outputting a clean, consolidated knowledge base
 * 
 * Usage: php consolidator.php
 * 
 * Requirements:
 * - composer install vlucas/phpdotenv
 * - .env file with API keys
 */

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;

class AICSVConsolidator {
    
    private $dotenv;
    private $config;
    private $allRecords = [];
    private $duplicateGroups = [];
    private $consolidatedRecords = [];
    private $stats = [
        'totalOriginal' => 0,
        'duplicatesFound' => 0,
        'nearDuplicatesFound' => 0,
        'aiMerged' => 0,
        'finalUnique' => 0,
        'apiCalls' => 0,
        'apiCost' => 0.0
    ];
    
    public function __construct() {
        $this->loadEnvironment();
        $this->initializeConfig();
    }
    
    private function loadEnvironment() {
        if (!file_exists('.env')) {
            $this->createSampleEnvFile();
            $this->log("❌ No .env file found. Created sample .env file. Please configure your API keys and run again.", 'error');
            exit(1);
        }
        
        $this->dotenv = Dotenv::createImmutable(__DIR__);
        $this->dotenv->load();
    }
    
    private function createSampleEnvFile() {
        $sampleEnv = <<<ENV
# AI API Configuration
# Uncomment and configure the API you want to use

# OpenAI Configuration
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-4
OPENAI_BASE_URL=https://api.openai.com/v1

# Anthropic Configuration  
ANTHROPIC_API_KEY=your_anthropic_api_key_here
ANTHROPIC_MODEL=claude-3-sonnet-20240229
ANTHROPIC_BASE_URL=https://api.anthropic.com

# Other AI Providers (add as needed)
# HUGGINGFACE_API_KEY=your_hf_key_here
# COHERE_API_KEY=your_cohere_key_here

# Consolidation Settings
AI_PROVIDER=openai
SIMILARITY_THRESHOLD=0.85
MAX_AI_CALLS_PER_RUN=100
ENABLE_AI_CONSOLIDATION=true
LOG_LEVEL=info

# CSV Configuration
INPUT_DIRECTORY=./csv_files
OUTPUT_FILE=consolidated_knowledge_base.csv
TERM_COLUMN=term
DEFINITION_COLUMN=definition
URL_COLUMN=url
ENV;
        
        file_put_contents('.env', $sampleEnv);
    }
    
    private function initializeConfig() {
        $this->config = [
            'ai_provider' => $_ENV['AI_PROVIDER'] ?? 'openai',
            'similarity_threshold' => floatval($_ENV['SIMILARITY_THRESHOLD'] ?? 0.85),
            'max_ai_calls' => intval($_ENV['MAX_AI_CALLS_PER_RUN'] ?? 100),
            'enable_ai' => filter_var($_ENV['ENABLE_AI_CONSOLIDATION'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'input_directory' => $_ENV['INPUT_DIRECTORY'] ?? './csv_files',
            'output_file' => $_ENV['OUTPUT_FILE'] ?? 'consolidated_knowledge_base.csv',
            'term_column' => $_ENV['TERM_COLUMN'] ?? 'term',
            'definition_column' => $_ENV['DEFINITION_COLUMN'] ?? 'definition',
            'url_column' => $_ENV['URL_COLUMN'] ?? 'url',
            'log_level' => $_ENV['LOG_LEVEL'] ?? 'info'
        ];
    }
    
    public function consolidate() {
        $this->log("🚀 Starting AI-powered CSV consolidation...", 'info');
        $this->log("AI Provider: " . strtoupper($this->config['ai_provider']), 'info');
        $this->log("Similarity Threshold: " . $this->config['similarity_threshold'], 'info');
        
        try {
            // Step 1: Load all CSV files
            $this->loadCSVFiles();
            
            // Step 2: Find potential duplicates using similarity matching
            $this->findDuplicateGroups();
            
            // Step 3: Use AI to intelligently consolidate duplicates
            if ($this->config['enable_ai']) {
                $this->aiConsolidateDuplicates();
            } else {
                $this->basicConsolidateDuplicates();
            }
            
            // Step 4: Create final output
            $this->generateOutput();
            
            $this->displayFinalStats();
            
        } catch (Exception $e) {
            $this->log("❌ Error during consolidation: " . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    private function loadCSVFiles() {
        $this->log("📁 Loading CSV files from: " . $this->config['input_directory'], 'info');
        
        if (!is_dir($this->config['input_directory'])) {
            mkdir($this->config['input_directory'], 0755, true);
            $this->log("Created input directory. Please place your CSV files there.", 'warning');
            return;
        }
        
        $files = glob($this->config['input_directory'] . '/*.csv');
        
        if (empty($files)) {
            throw new Exception("No CSV files found in " . $this->config['input_directory']);
        }
        
        foreach ($files as $file) {
            $this->log("Reading: " . basename($file), 'debug');
            $records = $this->parseCSVFile($file);
            
            foreach ($records as $record) {
                $record['_source_file'] = basename($file);
                $this->allRecords[] = $record;
            }
        }
        
        $this->stats['totalOriginal'] = count($this->allRecords);
        $this->log("✅ Loaded {$this->stats['totalOriginal']} records from " . count($files) . " files", 'info');
    }
    
    private function parseCSVFile($filePath) {
        $records = [];
        
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $headers = fgetcsv($handle);
            
            // Clean headers
            $headers = array_map('trim', $headers);
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) === count($headers)) {
                    $record = array_combine($headers, $data);
                    
                    // Skip empty records
                    if (!empty(trim($record[$this->config['term_column']] ?? ''))) {
                        $records[] = $record;
                    }
                }
            }
            
            fclose($handle);
        }
        
        return $records;
    }
    
    private function findDuplicateGroups() {
        $this->log("🔍 Finding duplicate and near-duplicate entries...", 'info');
        
        $processed = [];
        $totalRecords = count($this->allRecords);
        
        for ($i = 0; $i < $totalRecords; $i++) {
            if (in_array($i, $processed)) continue;
            
            $currentRecord = $this->allRecords[$i];
            $group = [$i];
            $processed[] = $i;
            
            // Find similar records
            for ($j = $i + 1; $j < $totalRecords; $j++) {
                if (in_array($j, $processed)) continue;
                
                $compareRecord = $this->allRecords[$j];
                
                if ($this->areRecordsSimilar($currentRecord, $compareRecord)) {
                    $group[] = $j;
                    $processed[] = $j;
                }
            }
            
            $this->duplicateGroups[] = $group;
            
            if (count($group) > 1) {
                if (count($group) == 2) {
                    $this->stats['duplicatesFound']++;
                } else {
                    $this->stats['nearDuplicatesFound']++;
                }
            }
            
            // Progress indicator
            if ($i % 100 == 0) {
                $progress = round(($i / $totalRecords) * 100, 1);
                $this->log("Progress: {$progress}% ({$i}/{$totalRecords})", 'debug');
            }
        }
        
        $duplicateCount = $this->stats['duplicatesFound'] + $this->stats['nearDuplicatesFound'];
        $this->log("Found {$duplicateCount} groups with duplicates/near-duplicates", 'info');
    }
    
    private function areRecordsSimilar($record1, $record2) {
        $term1 = trim($record1[$this->config['term_column']] ?? '');
        $term2 = trim($record2[$this->config['term_column']] ?? '');
        $def1 = trim($record1[$this->config['definition_column']] ?? '');
        $def2 = trim($record2[$this->config['definition_column']] ?? '');
        
        $termSimilarity = $this->calculateSimilarity($term1, $term2);
        $defSimilarity = $this->calculateSimilarity($def1, $def2);
        
        // Consider records similar if either term or definition meets threshold
        return max($termSimilarity, $defSimilarity) >= $this->config['similarity_threshold'];
    }
    
    private function calculateSimilarity($str1, $str2) {
        if (empty($str1) || empty($str2)) return 0;
        
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if ($str1 === $str2) return 1.0;
        
        $maxLen = max(strlen($str1), strlen($str2));
        $distance = levenshtein($str1, $str2);
        
        return ($maxLen - $distance) / $maxLen;
    }
    
    private function aiConsolidateDuplicates() {
        $this->log("🤖 Using AI to consolidate duplicate groups...", 'info');
        
        $duplicateGroups = array_filter($this->duplicateGroups, function($group) {
            return count($group) > 1;
        });
        
        $totalGroups = count($duplicateGroups);
        $this->log("Processing {$totalGroups} duplicate groups with AI", 'info');
        
        foreach ($duplicateGroups as $index => $group) {
            if ($this->stats['apiCalls'] >= $this->config['max_ai_calls']) {
                $this->log("⚠️  Reached max AI calls limit ({$this->config['max_ai_calls']})", 'warning');
                break;
            }
            
            $records = array_map(function($idx) {
                return $this->allRecords[$idx];
            }, $group);
            
            try {
                $consolidatedRecord = $this->aiMergeRecords($records);
                $this->consolidatedRecords[] = $consolidatedRecord;
                $this->stats['aiMerged']++;
                
                $progress = round((($index + 1) / $totalGroups) * 100, 1);
                $this->log("AI Progress: {$progress}% ({$index + 1}/{$totalGroups})", 'debug');
                
            } catch (Exception $e) {
                $this->log("⚠️  AI merge failed for group, using basic merge: " . $e->getMessage(), 'warning');
                $this->consolidatedRecords[] = $this->basicMergeRecords($records);
            }
            
            // Rate limiting
            usleep(100000); // 0.1 second delay between API calls
        }
        
        // Add unique records (groups with only 1 record)
        $uniqueGroups = array_filter($this->duplicateGroups, function($group) {
            return count($group) === 1;
        });
        
        foreach ($uniqueGroups as $group) {
            $this->consolidatedRecords[] = $this->allRecords[$group[0]];
        }
    }
    
    private function aiMergeRecords($records) {
        $prompt = $this->buildMergePrompt($records);
        
        switch ($this->config['ai_provider']) {
            case 'openai':
                return $this->callOpenAI($prompt);
            case 'anthropic':
                return $this->callAnthropic($prompt);
            default:
                throw new Exception("Unsupported AI provider: " . $this->config['ai_provider']);
        }
    }
    
    private function buildMergePrompt($records) {
        $termCol = $this->config['term_column'];
        $defCol = $this->config['definition_column'];
        $urlCol = $this->config['url_column'];
        
        $prompt = "You are an expert at consolidating knowledge base entries. ";
        $prompt .= "Below are " . count($records) . " similar entries that need to be merged into one optimal record.\n\n";
        
        $prompt .= "RECORDS TO MERGE:\n";
        foreach ($records as $i => $record) {
            $prompt .= "Entry " . ($i + 1) . ":\n";
            $prompt .= "Term: " . ($record[$termCol] ?? 'N/A') . "\n";
            $prompt .= "Definition: " . ($record[$defCol] ?? 'N/A') . "\n";
            if (!empty($record[$urlCol])) {
                $prompt .= "URL: " . $record[$urlCol] . "\n";
            }
            $prompt .= "Source: " . ($record['_source_file'] ?? 'Unknown') . "\n\n";
        }
        
        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "1. Create the best possible merged entry\n";
        $prompt .= "2. Choose the most accurate and clear term name\n";
        $prompt .= "3. Combine definitions to create one comprehensive, clear definition\n";
        $prompt .= "4. Include all relevant URLs, separated by semicolons\n";
        $prompt .= "5. Respond ONLY with valid JSON in this exact format:\n\n";
        
        $prompt .= "{\n";
        $prompt .= "  \"$termCol\": \"merged term here\",\n";
        $prompt .= "  \"$defCol\": \"merged definition here\",\n";
        if (!empty($urlCol)) {
            $prompt .= "  \"$urlCol\": \"url1; url2; url3\",\n";
        }
        $prompt .= "  \"sources_merged\": " . count($records) . ",\n";
        $prompt .= "  \"merge_confidence\": \"high|medium|low\"\n";
        $prompt .= "}";
        
        return $prompt;
    }
    
    private function callOpenAI($prompt) {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
        $model = $_ENV['OPENAI_MODEL'] ?? 'gpt-4';
        $baseUrl = $_ENV['OPENAI_BASE_URL'] ?? 'https://api.openai.com/v1';
        
        if (empty($apiKey)) {
            throw new Exception("OpenAI API key not configured");
        }
        
        $data = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.1
        ];
        
        $response = $this->makeHttpRequest(
            $baseUrl . '/chat/completions',
            $data,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        );
        
        $this->stats['apiCalls']++;
        $this->stats['apiCost'] += $this->estimateOpenAICost($model, $prompt, $response['choices'][0]['message']['content'] ?? '');
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        return $this->parseAIResponse($content);
    }
    
    private function callAnthropic($prompt) {
        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
        $model = $_ENV['ANTHROPIC_MODEL'] ?? 'claude-3-sonnet-20240229';
        $baseUrl = $_ENV['ANTHROPIC_BASE_URL'] ?? 'https://api.anthropic.com';
        
        if (empty($apiKey)) {
            throw new Exception("Anthropic API key not configured");
        }
        
        $data = [
            'model' => $model,
            'max_tokens' => 1000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $response = $this->makeHttpRequest(
            $baseUrl . '/v1/messages',
            $data,
            [
                'x-api-key: ' . $apiKey,
                'Content-Type: application/json',
                'anthropic-version: 2023-06-01'
            ]
        );
        
        $this->stats['apiCalls']++;
        $this->stats['apiCost'] += $this->estimateAnthropicCost($model, $prompt, $response['content'][0]['text'] ?? '');
        
        $content = $response['content'][0]['text'] ?? '';
        return $this->parseAIResponse($content);
    }
    
    private function makeHttpRequest($url, $data, $headers) {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if ($error) {
            throw new Exception("HTTP request failed: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API request failed with status {$httpCode}: " . $response);
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }
        
        return $decoded;
    }
    
    private function parseAIResponse($content) {
        // Extract JSON from response (handle cases where AI adds extra text)
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');
        
        if ($jsonStart === false || $jsonEnd === false) {
            throw new Exception("No valid JSON found in AI response");
        }
        
        $jsonString = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
        $parsed = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in AI response: " . json_last_error_msg());
        }
        
        return $parsed;
    }
    
    private function basicConsolidateDuplicates() {
        $this->log("📝 Using basic consolidation (AI disabled)", 'info');
        
        foreach ($this->duplicateGroups as $group) {
            $records = array_map(function($idx) {
                return $this->allRecords[$idx];
            }, $group);
            
            if (count($group) > 1) {
                $this->consolidatedRecords[] = $this->basicMergeRecords($records);
            } else {
                $this->consolidatedRecords[] = $records[0];
            }
        }
    }
    
    private function basicMergeRecords($records) {
        $termCol = $this->config['term_column'];
        $defCol = $this->config['definition_column'];
        $urlCol = $this->config['url_column'];
        
        // Select best term (longest non-empty)
        $terms = array_filter(array_column($records, $termCol));
        $bestTerm = $terms ? max($terms, function($a, $b) { return strlen($a) - strlen($b); }) : '';
        
        // Merge definitions
        $definitions = array_filter(array_column($records, $defCol));
        $mergedDef = $definitions ? implode(' | ', array_unique($definitions)) : '';
        
        // Merge URLs
        $urls = array_filter(array_column($records, $urlCol));
        $mergedUrls = $urls ? implode('; ', array_unique($urls)) : '';
        
        return [
            $termCol => $bestTerm,
            $defCol => $mergedDef,
            $urlCol => $mergedUrls,
            'sources_merged' => count($records),
            'merge_method' => 'basic'
        ];
    }
    
    private function generateOutput() {
        $this->stats['finalUnique'] = count($this->consolidatedRecords);
        
        // Sort alphabetically by term
        usort($this->consolidatedRecords, function($a, $b) {
            $termCol = $this->config['term_column'];
            return strcasecmp($a[$termCol] ?? '', $b[$termCol] ?? '');
        });
        
        // Write to CSV
        $outputPath = $this->config['output_file'];
        $handle = fopen($outputPath, 'w');
        
        if ($handle === false) {
            throw new Exception("Cannot write to output file: " . $outputPath);
        }
        
        // Write headers
        if (!empty($this->consolidatedRecords)) {
            fputcsv($handle, array_keys($this->consolidatedRecords[0]));
            
            // Write data
            foreach ($this->consolidatedRecords as $record) {
                fputcsv($handle, $record);
            }
        }
        
        fclose($handle);
        
        $this->log("✅ Consolidated knowledge base saved to: " . $outputPath, 'info');
    }
    
    private function estimateOpenAICost($model, $prompt, $response) {
        // Rough cost estimates (update based on current pricing)
        $inputTokens = str_word_count($prompt) * 1.3; // Rough token estimate
        $outputTokens = str_word_count($response) * 1.3;
        
        $costs = [
            'gpt-4' => ['input' => 0.03/1000, 'output' => 0.06/1000],
            'gpt-3.5-turbo' => ['input' => 0.001/1000, 'output' => 0.002/1000]
        ];
        
        $rates = $costs[$model] ?? $costs['gpt-4'];
        return ($inputTokens * $rates['input']) + ($outputTokens * $rates['output']);
    }
    
    private function estimateAnthropicCost($model, $prompt, $response) {
        $inputTokens = str_word_count($prompt) * 1.3;
        $outputTokens = str_word_count($response) * 1.3;
        
        // Anthropic pricing (update based on current rates)
        return ($inputTokens * 0.015/1000) + ($outputTokens * 0.075/1000);
    }
    
    private function displayFinalStats() {
        $this->log("\n" . str_repeat("=", 60), 'info');
        $this->log("📊 CONSOLIDATION COMPLETE - FINAL STATISTICS", 'info');
        $this->log(str_repeat("=", 60), 'info');
        
        $this->log("📁 Original Records: " . number_format($this->stats['totalOriginal']), 'info');
        $this->log("🔍 Duplicate Pairs Found: " . number_format($this->stats['duplicatesFound']), 'info');
        $this->log("🔍 Near-Duplicate Groups: " . number_format($this->stats['nearDuplicatesFound']), 'info');
        $this->log("🤖 AI-Merged Records: " . number_format($this->stats['aiMerged']), 'info');
        $this->log("✨ Final Unique Records: " . number_format($this->stats['finalUnique']), 'info');
        
        $reduction = $this->stats['totalOriginal'] > 0 ? 
            round((($this->stats['totalOriginal'] - $this->stats['finalUnique']) / $this->stats['totalOriginal']) * 100, 1) : 0;
        $this->log("📉 Size Reduction: {$reduction}%", 'info');
        
        if ($this->config['enable_ai']) {
            $this->log("🔧 API Calls Made: " . number_format($this->stats['apiCalls']), 'info');
            $this->log("💰 Estimated Cost: $" . number_format($this->stats['apiCost'], 4), 'info');
        }
        
        $this->log("📄 Output File: " . $this->config['output_file'], 'info');
        $this->log(str_repeat("=", 60) . "\n", 'info');
    }
    
    private function log($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $levelColors = [
            'debug' => "\033[0;37m",   // Gray
            'info' => "\033[0;32m",    // Green  
            'warning' => "\033[0;33m", // Yellow
            'error' => "\033[0;31m"    // Red
        ];
        
        $reset = "\033[0m";
        $color = $levelColors[$level] ?? $levelColors['info'];
        
        echo $color . "[{$timestamp}] " . strtoupper($level) . ": {$message}" . $reset . "\n";
        
        // Also log to file
        $logFile = 'consolidation.log';
        file_put_contents($logFile, "[{$timestamp}] " . strtoupper($level) . ": {$message}\n", FILE_APPEND);
    }
}

// Usage example and CLI interface
if (php_sapi_name() === 'cli') {
    try {
        echo "\n🚀 AI-Powered CSV Knowledge Base Consolidator\n";
        echo "=============================================\n\n";
        
        $consolidator = new AICSVConsolidator();
        $consolidator->consolidate();
        
        echo "\n✅ Consolidation completed successfully!\n";
        echo "Check the output file and consolidation.log for details.\n\n";
        
    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
} else {
    echo "This script is designed to run from the command line.\n";
    echo "Usage: php consolidator.php\n";
}

?>
