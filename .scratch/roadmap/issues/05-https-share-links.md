# 05: Share links are `https` behind the proxy

**What to build:** Share links generated behind the external HTTPS proxy use `https`, so recipients' browsers don't block them or warn about an insecure connection.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Trusted proxies are configured so generated URLs respect the forwarded scheme.
- [ ] A newly created Share's link starts with `https://` when the request arrives through the proxy with `X-Forwarded-Proto: https`, and its signature validates.
- [ ] Feature test covering the forwarded-scheme case, plus a manual check through the real proxy.
