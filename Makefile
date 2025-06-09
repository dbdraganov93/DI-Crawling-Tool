up:
	@echo "❗ This will start the diCrawler project for the PRODUCTION environment."
	@read -p "⚠️  Are you sure you are on the correct environment? (y/N): " confirm && [ "$$confirm" = "y" ] || (echo "❌ Aborted." && exit 1)
	@echo "🧹 Cleaning migrations..."
	rm -f migrations/*.php
	@echo "🚀 Starting app  for PRODUCTION ENVIORMENT (without override)..."
	COMPOSE_IGNORE_ORPHANS=True docker-compose -f docker-compose.yml up --build

up-local:
	@echo "❗ This will start the diCrawler project for the LOCAL environment (with tools like Portainer)."
	@read -p "⚠️  Are you sure you are on the LOCAL environment? (y/N): " confirm && [ "$$confirm" = "y" ] || (echo "❌ Aborted." && exit 1)
	@echo "🔍 Checking for containers with conflicting names..."
	@conflicts=$$(docker ps -a --format '{{.Names}}' | grep -E 'dicrawler_app|dicrawler_db|dicrawler_grafana|portainer' || true); \
	if [ -n "$$conflicts" ]; then \
		echo "⚠️  Found conflicting containers:"; \
		echo "$$conflicts"; \
		read -p "🧨 Do you want to remove these containers to avoid conflict? (y/N): " remove && [ "$$remove" = "y" ] && docker rm -f $$conflicts || (echo "❌ Aborted due to container conflict." && exit 1); \
	else \
		echo "✅ No container conflicts found."; \
	fi
	@echo "🧹 Cleaning migrations..."
	rm -f migrations/*.php
	@echo "🚀 Starting app for LOCAL ENVIORMENT (with override docker-compose)..."
	COMPOSE_IGNORE_ORPHANS=True docker-compose up --build
