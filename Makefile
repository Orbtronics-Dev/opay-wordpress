export:
	zip -r opay-payment-gateway.zip . \
		--exclude='.git/*' \
		--exclude='.github/*' \
		--exclude='.claude/*' \
		--exclude='.gitignore' \
		--exclude='*.zip'
