# Tailwind + Vite for the redesigned screens

Status: accepted

Phase 1 screens are plain Blade with a hand-written stylesheet inherited from the legacy stack, served as a static file with no build step. The Phase 2 redesign covers every screen. For it we're adopting Tailwind CSS 4 built with Vite, which Laravel's default scaffold already ships in the repo, unused until now. The cost is a Node build stage in the multi-stage Dockerfile, alongside the existing bpmn-js vendoring stage. Runtime is unchanged: assets are still built at image build time and served by nginx, with no CDN. We rejected keeping hand-written CSS, because restyling every screen by hand is slower and drifts. Once the redesign is done, the legacy stylesheet is removed. The existing editor/viewer JavaScript is migrated into the Vite pipeline only when a ticket needs to touch it.
