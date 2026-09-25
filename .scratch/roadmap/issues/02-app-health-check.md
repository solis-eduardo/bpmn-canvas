# 02: The app service has a health check and the web service waits for it

**What to build:** A deploy never serves 502s while the application boots: the app service reports healthy only when PHP-FPM answers and the database is reachable, and the web service starts routing only after that.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] The app service has a health check covering PHP-FPM and the database connection.
- [ ] The web service depends on app being healthy.
- [ ] Verified manually on the Docker stack: during `docker compose up`, web doesn't route to app before it's healthy, and stopping the database turns app unhealthy.
