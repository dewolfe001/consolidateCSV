#!/bin/bash

# 🚀 AI-Powered CSV Consolidator Setup Script
# This script sets up the environment and provides easy commands

set -e

echo "🚀 AI-Powered CSV Consolidator Setup"
echo "====================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}$1${NC}"
}

# Check if PHP is installed
check_php() {
    print_header "Checking PHP installation..."
    
    if ! command -v php &> /dev/null; then
        print_error "PHP is not installed. Please install PHP 7.4+ and try again."
        exit 1
    fi
    
    PHP_VERSION=$(php -v | grep -oP 'PHP \K[0-9]+\.[0-9]+')
    print_status "PHP $PHP_VERSION detected ✓"
    
    # Check required extensions
    EXTENSIONS=("curl" "json")
    for ext in "${EXTENSIONS[@]}"; do
        if php -m | grep -q "$ext"; then
            print_status "PHP extension '$ext' found ✓"
        else
            print_error "Required PHP extension '$ext' is missing"
            exit 1
        fi
    done
}

# Check if Composer is installed
check_composer() {
    print_header "Checking Composer installation..."
    
    if ! command -v composer &> /dev/null; then
        print_warning "Composer is not installed globally."
        print_status "Downloading Composer locally..."
        
        curl -sS https://getcomposer.org/installer | php
        chmod +x composer.phar
        COMPOSER_CMD="./composer.phar"
    else
        print_status "Composer found ✓"
        COMPOSER_CMD="composer"
    fi
}

# Install dependencies
install_dependencies() {
    print_header "Installing dependencies..."
    
    if [ ! -f "composer.json" ]; then
        print_error "composer.json not found. Make sure you're in the correct directory."
        exit 1
    fi
    
    $COMPOSER_CMD install --no-dev --optimize-autoloader
    print_status "Dependencies installed ✓"
}

# Create directory structure
setup_directories() {
    print_header "Setting up directory structure..."
    
    mkdir -p csv_files
    mkdir -p output
    mkdir -p logs
    
    print_status "Created csv_files/ directory for input files"
    print_status "Created output/ directory for results"
    print_status "Created logs/ directory for log files"
}

# Create sample .env if it doesn't exist
setup_env_file() {
    print_header "Setting up environment configuration..."
    
    if [ ! -f ".env" ]; then
        print_status "Creating sample .env file..."
        
        cat > .env << 'EOF'
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

# Consolidation Settings
AI_PROVIDER=openai
SIMILARITY_THRESHOLD=0.85
MAX_AI_CALLS_PER_RUN=100
ENABLE_AI_CONSOLIDATION=true
LOG_LEVEL=info

# CSV Configuration
INPUT_DIRECTORY=./csv_files
OUTPUT_FILE=./output/consolidated_knowledge_base.csv
TERM_COLUMN=term
DEFINITION_COLUMN=definition
URL_COLUMN=url
EOF
        
        print_status "Sample .env file created"
        print_warning "Please edit .env file and add your API keys before running the consolidator"
    else
        print_status ".env file already exists ✓"
    fi
}

# Create helper scripts
create_helper_scripts() {
    print_header "Creating helper scripts..."
    
    # Create run script
    cat > run.sh << 'EOF'
#!/bin/bash
echo "🚀 Starting CSV Consolidation..."
php consolidator.php
EOF
    chmod +x run.sh
    
    # Create validate script
    cat > validate.sh << 'EOF'
#!/bin/bash
echo "🔍 Validating environment..."

# Check .env file
if [ ! -f ".env" ]; then
    echo "❌ .env file not found"
    exit 1
fi

# Check for API key
if grep -q "your_.*_api_key_here" .env; then
    echo "⚠️  Warning: Default API keys detected in .env file"
    echo "   Please update with your actual API keys"
fi

# Check input directory
if [ ! -d "csv_files" ]; then
    echo "❌ csv_files directory not found"
    exit 1
fi

# Count CSV files
CSV_COUNT=$(find csv_files -name "*.csv" | wc -l)
echo "📁 Found $CSV_COUNT CSV files in csv_files/"

if [ $CSV_COUNT -eq 0 ]; then
    echo "⚠️  No CSV files found. Please add CSV files to csv_files/ directory"
else
    echo "✅ Environment validation passed"
fi
EOF
    chmod +x validate.sh
    
    # Create cost estimator script
    cat > estimate_cost.sh << 'EOF'
#!/bin/bash
echo "💰 Cost Estimation Tool"
echo "======================="

if [ ! -d "csv_files" ]; then
    echo "❌ csv_files directory not found"
    exit 1
fi

# Count total records
TOTAL_RECORDS=0
for file in csv_files/*.csv; do
    if [ -f "$file" ]; then
        RECORDS=$(tail -n +2 "$file" | wc -l)
        TOTAL_RECORDS=$((TOTAL_RECORDS + RECORDS))
        echo "📄 $(basename "$file"): $RECORDS records"
    fi
done

echo "📊 Total records: $TOTAL_RECORDS"

# Estimate duplicates (rough estimate: 20% duplicates)
ESTIMATED_DUPLICATES=$((TOTAL_RECORDS / 5))
echo "🔍 Estimated duplicates: ~$ESTIMATED_DUPLICATES"

# Cost estimates
GPT4_COST=$(echo "scale=2; $ESTIMATED_DUPLICATES * 0.045" | bc -l 2>/dev/null || echo "N/A")
GPT35_COST=$(echo "scale=2; $ESTIMATED_DUPLICATES * 0.003" | bc -l 2>/dev/null || echo "N/A")
CLAUDE_COST=$(echo "scale=2; $ESTIMATED_DUPLICATES * 0.045" | bc -l 2>/dev/null || echo "N/A")

echo ""
echo "💰 Estimated Costs:"
echo "   GPT-4: ~\$$GPT4_COST"
echo "   GPT-3.5-Turbo: ~\$$GPT35_COST"  
echo "   Claude: ~\$$CLAUDE_COST"
echo ""
echo "💡 Tip: Start with a small subset to test!"
EOF
    chmod +x estimate_cost.sh
    
    print_status "Helper scripts created:"
    print_status "  ./run.sh - Run the consolidator"
    print_status "  ./validate.sh - Validate environment setup"
    print_status "  ./estimate_cost.sh - Estimate processing costs"
}

# Create sample CSV files for testing
create_sample_files() {
    print_header "Creating sample CSV files for testing..."
    
    # Sample knowledge base 1
    cat > csv_files/sample_wordpress_terms.csv << 'EOF'
term,definition,url
WordPress Plugin,A software component that adds functionality to WordPress,https://wordpress.org/plugins/
WP Theme,A collection of files that determine the appearance of a WordPress site,https://wordpress.org/themes/
Custom Post Type,A content type other than the default posts and pages,https://developer.wordpress.org/plugins/post-types/
Shortcode,A small piece of code that creates a macro for WordPress content,https://developer.wordpress.org/plugins/shortcodes/
WordPress Hook,A way for one piece of code to interact with another piece of code,https://developer.wordpress.org/plugins/hooks/
EOF

    # Sample knowledge base 2 (with some duplicates)
    cat > csv_files/sample_web_design_terms.csv << 'EOF'
term,definition,url
WordPress Plugin,Software that extends WordPress functionality,https://docs.wordpress.org/plugins/
Responsive Design,Web design approach for optimal viewing across devices,https://developer.mozilla.org/en-US/docs/Learn/CSS/CSS_layout/Responsive_Design
CSS Framework,Pre-written CSS code library for faster development,https://getbootstrap.com/
WordPress Theme,Template files that control the visual appearance of WordPress,https://developer.wordpress.org/themes/
API Endpoint,A point where an API connects with a software program,https://developer.wordpress.org/rest-api/
SEO,Search Engine Optimization techniques,https://wordpress.org/plugins/wordpress-seo/
EOF

    print_status "Sample CSV files created in csv_files/"
    print_status "You can test with these files or replace them with your own"
}

# Main setup function
main() {
    echo ""
    print_header "🔧 Running setup checks..."
    
    check_php
    check_composer
    install_dependencies
    setup_directories
    setup_env_file
    create_helper_scripts
    
    # Ask if user wants sample files
    echo ""
    read -p "Create sample CSV files for testing? [y/N]: " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        create_sample_files
    fi
    
    echo ""
    print_header "🎉 Setup Complete!"
    echo ""
    print_status "Next steps:"
    echo "  1. Edit .env file and add your API keys"
    echo "  2. Place your CSV files in csv_files/ directory"
    echo "  3. Run ./validate.sh to check your setup"
    echo "  4. Run ./estimate_cost.sh to estimate processing costs"
    echo "  5. Run ./run.sh to start consolidation"
    echo ""
    print_status "For detailed instructions, see README.md"
}

# Run main function
main