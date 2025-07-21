# 🤖 AI-Powered CSV Knowledge Base Consolidator

A sophisticated PHP tool that intelligently consolidates multiple CSV files by finding duplicates and using AI APIs to create optimal merged entries.

## ✨ Features

- **Smart Duplicate Detection**: Uses advanced similarity algorithms to find exact and near-duplicate entries
- **AI-Powered Merging**: Leverages OpenAI GPT or Anthropic Claude to intelligently merge similar records
- **Multi-Provider Support**: Works with OpenAI, Anthropic, and extensible for other AI providers
- **Configurable Thresholds**: Adjustable similarity matching and processing limits
- **Comprehensive Logging**: Detailed progress tracking and cost estimation
- **Cost Management**: Built-in API call limits and cost tracking

## 🚀 Quick Start

### 1. Installation

```bash
# Clone or download the files
composer install
```

### 2. Configuration

Copy the sample `.env` file and configure your API keys:

```bash
# The script will create a sample .env file on first run, or create one manually:
```

**Sample .env Configuration:**
```env
# AI API Configuration
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-4
ANTHROPIC_API_KEY=your_anthropic_api_key_here

# Consolidation Settings
AI_PROVIDER=openai
SIMILARITY_THRESHOLD=0.85
MAX_AI_CALLS_PER_RUN=100
ENABLE_AI_CONSOLIDATION=true

# File Configuration
INPUT_DIRECTORY=./csv_files
OUTPUT_FILE=consolidated_knowledge_base.csv
TERM_COLUMN=term
DEFINITION_COLUMN=definition
URL_COLUMN=url
```

### 3. Prepare Your CSV Files

1. Create a `csv_files` directory (or use your configured `INPUT_DIRECTORY`)
2. Place your CSV files in this directory
3. Ensure your CSV files have headers that match your configuration

### 4. Run the Consolidation

```bash
php consolidator.php
```

## 📊 How It Works

### Step 1: Similarity Detection
- Loads all CSV files from the input directory
- Uses Levenshtein distance algorithm to find similar records
- Groups records that meet the similarity threshold

### Step 2: AI-Powered Consolidation
- Sends duplicate groups to your chosen AI provider
- AI analyzes the records and creates optimal merged entries
- Preserves all important information while eliminating redundancy

### Step 3: Output Generation
- Creates a clean, consolidated CSV file
- Sorts entries alphabetically
- Includes metadata about the merge process

## 🎛️ Configuration Options

### AI Provider Settings

**OpenAI Configuration:**
```env
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4          # or gpt-3.5-turbo for lower cost
OPENAI_BASE_URL=https://api.openai.com/v1
```

**Anthropic Configuration:**
```env
ANTHROPIC_API_KEY=ant-...
ANTHROPIC_MODEL=claude-3-sonnet-20240229
ANTHROPIC_BASE_URL=https://api.anthropic.com
```

### Processing Settings

| Setting | Description | Default |
|---------|-------------|---------|
| `SIMILARITY_THRESHOLD` | How similar records must be to merge (0-1) | 0.85 |
| `MAX_AI_CALLS_PER_RUN` | Maximum API calls per execution | 100 |
| `ENABLE_AI_CONSOLIDATION` | Use AI for merging vs basic merging | true |

### File Settings

| Setting | Description | Default |
|---------|-------------|---------|
| `INPUT_DIRECTORY` | Directory containing CSV files | ./csv_files |
| `OUTPUT_FILE` | Name of consolidated output file | consolidated_knowledge_base.csv |
| `TERM_COLUMN` | Column name for terms/titles | term |
| `DEFINITION_COLUMN` | Column name for definitions | definition |
| `URL_COLUMN` | Column name for URLs (optional) | url |

## 📈 Output & Statistics

The tool provides comprehensive statistics:

- **Original Records**: Total records loaded from all files
- **Duplicates Found**: Number of exact/near duplicate pairs
- **AI-Merged Records**: Records processed through AI
- **Final Unique Records**: Number of records in output
- **Size Reduction**: Percentage reduction in total records
- **API Calls & Cost**: Usage tracking for cost management

## 🔧 Advanced Usage

### Custom AI Prompts

The tool uses sophisticated prompts to ensure high-quality merges. The AI considers:
- Term accuracy and clarity
- Definition comprehensiveness
- URL preservation
- Source attribution

### Error Handling

- Automatic fallback to basic merging if AI fails
- Rate limiting to prevent API overuse
- Comprehensive logging for troubleshooting

### Performance Optimization

- Configurable API call limits
- Batch processing for large datasets
- Memory-efficient CSV handling

## 📝 Example Workflow

1. **Input Files:**
   ```
   csv_files/
   ├── knowledge_base.csv
   ├── wordpress_terms.csv
   └── business_definitions.csv
   ```

2. **Sample Input Records:**
   ```csv
   term,definition,url
   "WordPress Plugin","A software component that adds functionality","https://example.com/plugins"
   "WP Plugin","Software that extends WordPress features","https://docs.wordpress.org/plugins"
   ```

3. **AI-Merged Output:**
   ```csv
   term,definition,url,sources_merged,merge_confidence
   "WordPress Plugin","A software component that adds functionality to WordPress by extending its features","https://example.com/plugins; https://docs.wordpress.org/plugins",2,"high"
   ```

## 🛠️ Troubleshooting

### Common Issues

**"No CSV files found"**
- Check that your INPUT_DIRECTORY path is correct
- Ensure CSV files have .csv extension
- Verify file permissions

**"API key not configured"**
- Check your .env file exists and is in the same directory
- Verify API key format and validity
- Ensure you've selected the correct AI_PROVIDER

**"Invalid JSON in AI response"**
- Try reducing SIMILARITY_THRESHOLD to process smaller groups
- Switch to basic merging: `ENABLE_AI_CONSOLIDATION=false`
- Check API provider status

### Performance Tips

- Start with a small subset of files for testing
- Use `gpt-3.5-turbo` instead of `gpt-4` for faster/cheaper processing
- Adjust `MAX_AI_CALLS_PER_RUN` based on your budget
- Monitor the consolidation.log file for detailed progress

## 💡 Cost Management

### Estimated Costs (USD)

**OpenAI GPT-4:**
- ~$0.03-0.06 per merge operation
- 100 merges ≈ $3-6

**OpenAI GPT-3.5-Turbo:**
- ~$0.002-0.004 per merge operation  
- 100 merges ≈ $0.20-0.40

**Anthropic Claude:**
- ~$0.015-0.075 per merge operation
- 100 merges ≈ $1.50-7.50

### Cost Optimization

1. Set reasonable `MAX_AI_CALLS_PER_RUN` limits
2. Use higher `SIMILARITY_THRESHOLD` to reduce false positives
3. Start with cheaper models for testing
4. Monitor real-time cost estimates in the output

## 📄 License

This project is open source and available under the MIT License.

## 🤝 Contributing

Contributions are welcome! Areas for improvement:
- Additional AI provider integrations
- Advanced similarity algorithms
- Batch processing optimizations
- Web interface development

---

**Happy Consolidating!** 🎉