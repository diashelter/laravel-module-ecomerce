FROM node:24-alpine

WORKDIR /app

EXPOSE 5173

# Dependencies live in a named volume; install them when missing, then start Vite.
CMD ["sh", "-c", "[ -d node_modules/vite ] || npm install; npm run dev -- --host 0.0.0.0 --port 5173"]
