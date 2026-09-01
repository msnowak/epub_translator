# Third-party notices

EPUB Translator is distributed under the MIT License (see [LICENSE](LICENSE)).
It depends on the third-party components listed below, all of which are under
permissive licenses. This file exists to carry their attribution, which the
MIT, ISC, BSD, Apache-2.0 and OFL licenses all require when their code is
redistributed - which happens whenever you publish the built frontend bundle
or the Docker images.

The dependencies themselves are not vendored into this repository:
`backend/vendor/` and `frontend/node_modules/` are installed from
`composer.lock` and `package-lock.json`, and each installed package carries
its own licence file. What follows is a summary of what those lockfiles
resolve to.

## Source copied into this repository

**shadcn/ui** - MIT, Copyright (c) 2023 shadcn - <https://ui.shadcn.com/>

The components under `frontend/src/components/ui/` (`badge`, `button`,
`card`, `dialog`, `input`, `label`, `progress`, `select`, `sonner`, `table`,
`textarea`) started as copies from the shadcn/ui registry and have been
modified since. shadcn/ui is distributed as code you copy into your own tree
rather than as a package dependency, which is why it is named here and not in
the lists below.

## Fonts

**Geist Variable** - SIL Open Font License 1.1, Copyright 2024 The Geist
Project Authors - <https://github.com/vercel/geist-font>

Pulled in through `@fontsource-variable/geist` and bundled into the frontend
build as `.woff2` files, so the OFL requires the licence to travel with them.
The full text ships alongside the build at
[`frontend/public/licenses/geist-OFL.txt`](frontend/public/licenses/geist-OFL.txt),
which Vite copies verbatim into `dist/licenses/geist-OFL.txt`.

## Frontend - runtime dependencies

These end up inside the bundle served to the browser. Minification strips
every legal comment out of it - `dist/assets/index-*.js` carries no copyright
line of its own - which is what this file is here for.

Shipping the project as source, the way this repository does, covers that on
its own: these notices travel with the checkout, and the images are built by
whoever runs the build. Worth remembering only if that ever changes - an image
pushed to a registry, or `dist/` uploaded to a static host, reaches people
without this file, because the frontend build context is `./frontend` and the
root of the repository is outside it.

| Package | Licence |
| --- | --- |
| @base-ui/react | MIT |
| @fontsource-variable/geist | OFL-1.1 |
| @hookform/resolvers | MIT |
| @tanstack/react-query | MIT |
| @tanstack/react-virtual | MIT |
| class-variance-authority | Apache-2.0 |
| clsx | MIT |
| lucide-react | ISC |
| next-themes | MIT |
| react | MIT |
| react-dom | MIT |
| react-hook-form | MIT |
| react-router-dom | MIT |
| shadcn | MIT |
| sonner | MIT |
| tailwind-merge | MIT |
| tw-animate-css | MIT |
| zod | MIT |

`shadcn` is the registry CLI rather than something the application imports;
it sits in `dependencies` for the sake of `npx shadcn add`, and nothing it
pulls in reaches the bundle.

## Frontend - build and test tooling

Not shipped to users, listed for completeness.

| Package | Licence |
| --- | --- |
| @tailwindcss/vite | MIT |
| @testing-library/dom | MIT |
| @testing-library/jest-dom | MIT |
| @testing-library/react | MIT |
| @testing-library/user-event | MIT |
| @types/node | MIT |
| @types/react | MIT |
| @types/react-dom | MIT |
| @vitejs/plugin-react | MIT |
| @vitest/coverage-v8 | MIT |
| jsdom | MIT |
| msw | MIT |
| oxlint | MIT |
| tailwindcss | MIT |
| typescript | Apache-2.0 |
| vite | MIT |
| vitest | MIT |

Tailwind's CSS compiler reaches `lightningcss`, which is MPL-2.0. It is a
build-time tool that this project neither modifies nor redistributes, and the
CSS it emits is not a derivative of its source, so no MPL obligation attaches
to this project or to what it ships.

## Backend - runtime dependencies

These are installed into `vendor/` inside the production image.

| Package | Licence |
| --- | --- |
| api-platform/doctrine-orm | MIT |
| api-platform/symfony | MIT |
| doctrine/doctrine-bundle | MIT |
| doctrine/doctrine-migrations-bundle | MIT |
| doctrine/orm | MIT |
| lexik/jwt-authentication-bundle | MIT |
| monolog/monolog | MIT |
| nelmio/cors-bundle | MIT |
| phpdocumentor/reflection-docblock | MIT |
| phpstan/phpdoc-parser | MIT |
| symfony/* (asset, console, doctrine-messenger, dotenv, expression-language, filesystem, flex, framework-bundle, http-client, messenger, monolog-bridge, monolog-bundle, property-access, property-info, runtime, security-bundle, serializer, translation, twig-bundle, validator, yaml) | MIT |

Two transitive runtime packages are BSD-3-Clause rather than MIT:
`twig/twig` (Copyright (c) 2009-present, the Twig Team) and `lcobucci/jwt`
(Copyright (c) 2011-2023, Luís Cobucci).

## Backend - development dependencies

| Package | Licence |
| --- | --- |
| dama/doctrine-test-bundle | MIT |
| phpstan/phpstan | MIT |
| phpstan/phpstan-doctrine | MIT |
| phpstan/phpstan-symfony | MIT |
| phpunit/phpunit | BSD-3-Clause |
| symfony/browser-kit | MIT |
| symfony/css-selector | MIT |

PHPUnit and the `sebastian/*`, `phar-io/*` and `theseer/tokenizer` packages
it brings with it are BSD-3-Clause, Copyright (c) Sebastian Bergmann and
others. None of them are installed into the production image, which runs
`composer install --no-dev`.

## Container images

The Dockerfiles build on images that are redistributed if you publish the
result: `dunglas/frankenphp` (MIT), `caddy` (Apache-2.0), `node` (MIT, on
Alpine Linux) and `composer/composer` (MIT). Each carries its own licences
and the licences of the distribution packages inside it.

`compose.yaml` also runs `amir20/dozzle:v8` (MIT, Copyright (c) 2025 Amir
Raminfar) unmodified as the container-log viewer. It is not a base image -
nothing here builds on top of it - but it ships as part of the stack this
project hands you, so it is credited here too.

## Language models

The application talks to an [Ollama](https://ollama.com/) server (MIT) over
HTTP; neither Ollama nor any model is bundled here. The models you pull carry
their own terms - the default `llama3.1:8b` is under the Llama 3.1 Community
License, which is not an OSI-approved licence and carries its own use
restrictions. Checking the licence of the model you run is on you as the
operator; nothing in this project grants rights to it.

## Refreshing this file

After changing dependencies:

```bash
docker compose exec backend composer licenses
```

```bash
docker compose exec frontend npx license-checker-rspack --summary
```
