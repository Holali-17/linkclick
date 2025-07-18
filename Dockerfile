# Étape de construction - si vous avez besoin de builder des assets
FROM node:18 as builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
# RUN npm run build  # À décommenter si vous avez un build step

# Étape de production
FROM nginx:alpine
COPY --from=builder /app /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Correction des permissions pour Netlify
RUN chmod -R 755 /usr/share/nginx/html

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]