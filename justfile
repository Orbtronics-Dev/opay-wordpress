default:
    @just --list

export:
    @zip -r orbtronics-payment-gateway.zip . \
        --exclude='.git/*' \
        --exclude='.github/*' \
        --exclude='.claude/*' \
        --exclude='.gitignore' \
        --exclude='*.zip' \
        --exclude='.direnv/*' \
        --exclude='.envrc' \
        --exclude='*.nix' \
        --exclude='flake.lock' \
        --exclude='vendor/*' \
        --exclude='.php-cs-fixer.php'

format:
    @nix develop -c treefmt

lint:
    @nix develop -c treefmt --ci --config-file ./treefmt.lint.toml