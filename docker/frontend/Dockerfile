FROM node:20-alpine

WORKDIR /app

# Dependencies are installed by the developer via `docker compose exec frontend npm install`
# The node_modules volume in compose prevents the bind-mount from overwriting them.

EXPOSE 5173

# --host exposes Vite to all interfaces inside the container (required for Docker)
CMD ["npm", "run", "dev", "--", "--host"]
