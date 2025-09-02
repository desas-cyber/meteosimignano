#!/bin/bash

# Site Dependencies Audit Script for MAMP
# Analizza dipendenze di file .html, .js e .php rispettando .gitignore
# Uso: ./site_audit.sh [nome_sito] [profondità_max]

# Configurazione
MAMP_HTDOCS="/Applications/MAMP/htdocs"  # Modifica se MAMP è in posizione diversa
DEFAULT_DEPTH=5
OUTPUT_DIR="site_audits"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# File di interesse
TARGET_EXTENSIONS=("html" "js" "php")

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Funzione di help
show_help() {
    echo -e "${BLUE}MAMP Dependencies Audit Tool${NC}"
    echo ""
    echo "Analizza dipendenze di file .html, .js, .php rispettando .gitignore"
    echo ""
    echo "Uso: $0 [nome_sito] [profondità_max]"
    echo ""
    echo "Parametri:"
    echo "  nome_sito      Nome della cartella del sito in htdocs (opzionale)"
    echo "  profondità_max Livello massimo di cartelle da scandire (default: $DEFAULT_DEPTH)"
    echo ""
    echo "Esempi:"
    echo "  $0                    # Scansiona tutti i siti"
    echo "  $0 mio_sito           # Scansiona solo 'mio_sito'"
    echo "  $0 mio_sito 3         # Scansiona 'mio_sito' con profondità 3"
}

# Funzione per creare pattern di esclusione da .gitignore
create_find_excludes() {
    local gitignore_file="$1"
    local excludes=""
    
    if [[ -f "$gitignore_file" ]]; then
        while IFS= read -r line; do
            line=$(echo "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
            
            if [[ -z "$line" ]] || [[ "$line" =~ ^# ]] || [[ "$line" =~ ^! ]]; then
                continue
            fi
            
            if [[ "$line" =~ /$ ]]; then
                pattern="${line%/}"
                excludes="$excludes -path \"*/$pattern\" -prune -o"
            else
                if [[ "$line" =~ ^\* ]]; then
                    excludes="$excludes -name \"$line\" -prune -o"
                else
                    excludes="$excludes -name \"$line\" -prune -o -path \"*/$line\" -prune -o"
                fi
            fi
        done < "$gitignore_file"
    fi
    
    echo "$excludes"
}

# Funzione per estrarre dipendenze da file HTML
extract_html_dependencies() {
    local file="$1"
    local base_dir="$2"
    
    echo "    📄 HTML Dependencies:"
    
    # CSS files (link href)
    grep -iE '<link[^>]*href=' "$file" | grep -oE 'href="[^"]*"' | sed 's/href="//;s/"//' | while read -r dep; do
        if [[ ! "$dep" =~ ^(https?://|//) ]]; then
            local full_path=$(resolve_path "$base_dir" "$dep")
            echo "      🎨 CSS: $dep"
            [[ -f "$full_path" ]] && echo "        ✓ EXISTS: $full_path" || echo "        ✗ MISSING: $full_path"
        fi
    done
    
    # JavaScript files (script src)
    grep -iE '<script[^>]*src=' "$file" | grep -oE 'src="[^"]*"' | sed 's/src="//;s/"//' | while read -r dep; do
        if [[ ! "$dep" =~ ^(https?://|//) ]]; then
            local full_path=$(resolve_path "$base_dir" "$dep")
            echo "      📜 JS: $dep"
            [[ -f "$full_path" ]] && echo "        ✓ EXISTS: $full_path" || echo "        ✗ MISSING: $full_path"
        fi
    done
    
    # Images
    grep -iE '<img[^>]*src=' "$file" | grep -oE 'src="[^"]*"' | sed 's/src="//;s/"//' | while read -r dep; do
        if [[ ! "$dep" =~ ^(https?://|//) ]]; then
            local full_path=$(resolve_path "$base_dir" "$dep")
            echo "      🖼️  IMG: $dep"
            [[ -f "$full_path" ]] && echo "        ✓ EXISTS: $full_path" || echo "        ✗ MISSING: $full_path"
        fi
    done
    
    # PHP includes
    grep -iE 'include|require' "$file" | grep -oE '["'"'"'][^"'"'"']*["'"'"']' | sed 's/["\'"'"']//g' | while read -r dep; do
        if [[ "$dep" =~ \.(php|html)$ ]]; then
            local full_path=$(resolve_path "$base_dir" "$dep")
            echo "      🔗 PHP: $dep"
            [[ -f "$full_path" ]] && echo "        ✓ EXISTS: $full_path" || echo "        ✗ MISSING: $full_path"
        fi
    done
}

# Funzione per estrarre dipendenze da file JavaScript
extract_js_dependencies() {
    local file="$1"
    local base_dir="$2"
    
    echo "    📄 JavaScript Dependencies:"
    
    # Import statements
    grep -E "import.*from ['\"]" "$file" | grep -oE "from ['\"][^'\"]*['\"]" | sed "s/from ['\"]//" | sed "s/['\"]$//" | while read -r dep; do
        if [[ ! "$dep" =~ ^(https?://|//) ]] && [[ "$dep" =~ \.(js|ts)$ ]]; then
            local full_path=$(resolve_path "$base_dir" "$dep")
            echo "      📦 IMPORT: $dep"
            [[ -f "$full_path" ]] && echo "        ✓ EXISTS: $full_path" || echo "        ✗ MISSING: $full_path"
        fi
    done
    
    # Require statements
    grep -E "require\(['\"]" "$file" | grep -oE "['\"][^'\"]*['\"]" | sed "s/['\"]//g" | while read -r dep; do
        if [[ ! "$dep" =~ ^(https?://|//) ]] && [[ "$dep" =~ \.(js|json)$ ]]; then
            local full_path=$(resolve_path "$base_dir" "$dep")
            echo "      📦 REQUIRE: $dep"
            [[ -f "$full_path" ]] && echo "        ✓ EXISTS: $full_path" || echo "        ✗ MISSING: $full_path"
        fi
    done
}

# Funzione per estrarre dipendenze da file PHP
extract_php_dependencies() {
    local file="$1"
    local base_dir="$2"
    
    echo "    📄 PHP Dependencies:"
    
    # Debug: mostra prime 5 righe del file per verifica
    echo "      🔍 DEBUG - Prime righe del file:"
    head -5 "$file" | sed 's/^/        /'
    echo ""
    
    local found_deps=false
    
    # Pattern più flessibili per include/require
    # Versione 1: con parentesi
    grep -iE "(include|require)(_once)?\s*\(" "$file" | while read -r line; do
        echo "      🔍 FOUND LINE: $line"
        # Estrai il path dal contenuto tra virgolette
        local dep=$(echo "$line" | grep -oE "['\"]+[^'\"]+['\"]+\s*" | sed "s/['\"]//g" | sed 's/^\s*//;s/\s*$//' | head -1)
        if [[ -n "$dep" ]]; then
            found_deps=true
            local full_path=$(resolve_path "$base_dir" "$dep")
            echo "      🔗 PHP: $dep"
            [[ -f "$full_path" ]] && echo "        ✓ EXISTS: $full_path" || echo "        ✗ MISSING: $full_path"
        fi
    done
    
    # Versione 2: senza parentesi (stile alternativo)
    grep -iE "(include|require)(_once)?\s+['\"]" "$file" | while read -r line; do
        echo "      🔍 FOUND LINE (no parens): $line"
        local dep=$(echo "$line" | grep -oE "['\"][^'\"]+['\"]" | sed "s/['\"]//g" | head -1)
        if [[ -n "$dep" ]]; then
            found_deps=true
            local full_path=$(resolve_path "$base_dir" "$dep")
            echo "      🔗 PHP: $dep"
            [[ -f "$full_path" ]] && echo "        ✓ EXISTS: $full_path" || echo "        ✗ MISSING: $full_path"
        fi
    done
    
    # Versione 3: pattern con variabili PHP
    grep -iE "(include|require)(_once)?\s*.*\\\$" "$file" | while read -r line; do
        echo "      🔍 FOUND VARIABLE LINE: $line"
        echo "      ⚠️  Contains PHP variable - manual check needed"
    done
    
    # Pattern per namespace/use statements
    grep -E "^use\s+" "$file" | while read -r line; do
        echo "      📦 NAMESPACE: $line"
    done
    
    # WordPress specific
    if grep -q "wp-" "$file" || grep -q "wordpress" "$file" || grep -q "WP_" "$file"; then
        echo "      🌐 WordPress detected - checking common dependencies:"
        for wp_file in wp-config.php wp-load.php functions.php wp-blog-header.php; do
            if [[ -f "$base_dir/$wp_file" ]]; then
                echo "        ✓ WP: $wp_file"
            elif [[ -f "$base_dir/../$wp_file" ]]; then
                echo "        ✓ WP (parent): ../$wp_file"
            fi
        done
    fi
    
    # Check per composer autoload
    if grep -q "autoload" "$file" || grep -q "vendor/autoload" "$file"; then
        echo "      📦 Composer autoload detected"
        if [[ -f "$base_dir/vendor/autoload.php" ]]; then
            echo "        ✓ vendor/autoload.php"
        elif [[ -f "$base_dir/../vendor/autoload.php" ]]; then
            echo "        ✓ ../vendor/autoload.php"
        else
            echo "        ✗ vendor/autoload.php not found"
        fi
    fi
    
    # Cerca file .env
    if grep -q "\.env" "$file" || grep -q "_ENV\[" "$file"; then
        echo "      ⚙️  Environment config detected"
        if [[ -f "$base_dir/.env" ]]; then
            echo "        ✓ .env"
        else
            echo "        ✗ .env not found"
        fi
    fi
    
    # Se non ha trovato nulla, mostra un messaggio
    if ! $found_deps; then
        echo "      ℹ️  No standard include/require patterns found"
        echo "      💡 This might be a standalone file or uses dynamic includes"
    fi
}

# Funzione per risolvere path relativi
resolve_path() {
    local base_dir="$1"
    local relative_path="$2"
    
    if [[ "$relative_path" =~ ^/ ]]; then
        # Path assoluto dalla root del sito
        echo "$base_dir$relative_path"
    else
        # Path relativo
        echo "$base_dir/$relative_path"
    fi
}

# Funzione per analizzare un singolo file
analyze_file() {
    local file_path="$1"
    local site_path="$2"
    local relative_path="${file_path#$site_path/}"
    local file_dir=$(dirname "$file_path")
    local extension="${file_path##*.}"
    
    echo ""
    echo -e "  ${PURPLE}📁${NC} $relative_path"
    echo -e "    📊 Size: $(du -h "$file_path" 2>/dev/null | cut -f1)"
    echo -e "    📅 Modified: $(stat -f "%Sm" -t "%Y-%m-%d %H:%M" "$file_path" 2>/dev/null || date -r "$file_path" "+%Y-%m-%d %H:%M" 2>/dev/null || echo "N/A")"
    
    case "$extension" in
        "html")
            extract_html_dependencies "$file_path" "$site_path"
            ;;
        "js")
            extract_js_dependencies "$file_path" "$file_dir"
            ;;
        "php")
            extract_php_dependencies "$file_path" "$file_dir"
            ;;
    esac
}

# Funzione per analizzare un singolo sito
audit_site() {
    local site_name="$1"
    local max_depth="$2"
    local site_path="$MAMP_HTDOCS/$site_name"
    local output_file="$OUTPUT_DIR/${site_name}_dependencies_$TIMESTAMP.txt"
    
    if [[ ! -d "$site_path" ]]; then
        echo -e "${RED}✗${NC} Sito non trovato: $site_path"
        return 1
    fi
    
    echo -e "${BLUE}🔍 Analizzando dipendenze:${NC} $site_name"
    
    # Prepara esclusioni da .gitignore
    local excludes=""
    if [[ -f "$site_path/.gitignore" ]]; then
        excludes=$(create_find_excludes "$site_path/.gitignore")
        echo -e "${GREEN}✓${NC} Usando esclusioni da .gitignore"
    fi
    
    # Trova tutti i file target
    local find_cmd="find \"$site_path\" -maxdepth $max_depth"
    
    if [[ -n "$excludes" ]]; then
        find_cmd="$find_cmd $excludes"
    fi
    
    # Aggiungi filtri per estensioni
    find_cmd="$find_cmd -type f \( -name '*.html' -o -name '*.js' -o -name '*.php' \) -print"
    
    # Crea header del report
    {
        echo "=================================="
        echo "DEPENDENCIES AUDIT REPORT"
        echo "=================================="
        echo "Site: $site_name"
        echo "Path: $site_path"
        echo "Date: $(date)"
        echo "Max Depth: $max_depth"
        echo "Target Extensions: ${TARGET_EXTENSIONS[*]}"
        echo ""
        
        # Statistiche rapide
        local total_html=$(eval "$find_cmd" | grep -c '\.html$' || echo 0)
        local total_js=$(eval "$find_cmd" | grep -c '\.js$' || echo 0)
        local total_php=$(eval "$find_cmd" | grep -c '\.php$' || echo 0)
        
        echo "SUMMARY:"
        echo "--------"
        echo "HTML files: $total_html"
        echo "JavaScript files: $total_js"
        echo "PHP files: $total_php"
        echo "Total target files: $((total_html + total_js + total_php))"
        echo ""
        echo "DETAILED ANALYSIS:"
        echo "=================="
    } > "$output_file"
    
    # Analizza ogni file e salva nel report
    local file_count=0
    eval "$find_cmd" | sort | while read -r file_path; do
        {
            analyze_file "$file_path" "$site_path"
        } >> "$output_file"
        ((file_count++))
        
        # Progress indicator
        if (( file_count % 5 == 0 )); then
            echo -e "${YELLOW}📊${NC} Processati $file_count file..."
        fi
    done
    
    echo -e "${GREEN}✅${NC} Report salvato: $output_file"
    
    # Mostra summary
    echo -e "${YELLOW}📋 Summary:${NC}"
    tail -n +13 "$output_file" | head -10 | sed 's/^/  /'
}

# Main script
main() {
    if [[ "$1" == "-h" ]] || [[ "$1" == "--help" ]]; then
        show_help
        exit 0
    fi
    
    if [[ ! -d "$MAMP_HTDOCS" ]]; then
        echo -e "${RED}✗${NC} MAMP htdocs non trovato in: $MAMP_HTDOCS"
        echo "Modifica la variabile MAMP_HTDOCS nello script"
        exit 1
    fi
    
    mkdir -p "$OUTPUT_DIR"
    
    local site_name="$1"
    local max_depth="${2:-$DEFAULT_DEPTH}"
    
    echo -e "${BLUE}🔍 MAMP Dependencies Audit Tool${NC}"
    echo "==============================="
    echo -e "Target: ${GREEN}.html, .js, .php${NC} files"
    echo ""
    
    if [[ -n "$site_name" ]]; then
        audit_site "$site_name" "$max_depth"
    else
        echo -e "${YELLOW}📂 Scansionando tutti i siti...${NC}"
        
        local site_count=0
        for site_dir in "$MAMP_HTDOCS"/*; do
            if [[ -d "$site_dir" ]]; then
                local dir_name=$(basename "$site_dir")
                
                if [[ "$dir_name" =~ ^(\..*|Dashboard|favicon\.ico)$ ]]; then
                    continue
                fi
                
                audit_site "$dir_name" "$max_depth"
                ((site_count++))
                echo ""
            fi
        done
        
        if [[ $site_count -eq 0 ]]; then
            echo -e "${YELLOW}⚠${NC} Nessun sito trovato"
        else
            echo -e "${GREEN}🎉${NC} Completata analisi di $site_count siti"
        fi
    fi
    
    echo ""
    echo -e "${BLUE}📁 Report in:${NC} $OUTPUT_DIR/"
    echo -e "${YELLOW}💡 Tip:${NC} Controlla i file 'MISSING' per dipendenze rotte"
}

# Esegui script
main "$@"