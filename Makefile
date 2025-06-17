up:
	@echo "❗ Starting diCrawler for PRODUCTION environment (non-interactive, without override)"
	@echo "🧹 Cleaning migrations..."
	rm -f migrations/*.php
	@echo "🚀 Starting app for PRODUCTION (no override docker-compose)..."
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
	@echo "🧼 Checking for lingering processes on port 9000..."
	@pid=$$(lsof -ti :9000) && [ -n "$$pid" ] && echo "🛑 Killing process on port 9000 (PID: $$pid)" && kill -9 $$pid || echo "✅ No process on port 9000."
	@echo "🧹 Removing portainer_data volume (optional)..."
	-docker volume rm portainer_data >/dev/null 2>&1 || true
	@echo "🚀 Starting app for LOCAL (with override docker-compose)..."
	COMPOSE_IGNORE_ORPHANS=True docker-compose up --build
