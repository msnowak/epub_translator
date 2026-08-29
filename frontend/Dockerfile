FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# Vite wypieka VITE_API_URL w bundlu w czasie BUDOWANIA, nie startu. To musi
# byc argument builda - zmienna srodowiskowa kontenera przyszlaby za pozno
# i front strzelalby pod adres zaszyty w obrazie.
ARG VITE_API_URL=http://localhost:8000
ENV VITE_API_URL=$VITE_API_URL

COPY . .
RUN npm run build

FROM caddy:2-alpine AS prod

COPY --from=build /app/dist /srv
COPY Caddyfile /etc/caddy/Caddyfile
