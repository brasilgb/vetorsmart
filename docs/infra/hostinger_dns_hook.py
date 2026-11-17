#!/usr/bin/env python3
"""Certbot DNS-01 hook for zones hosted on Hostinger DNS (VetorSmart wildcard certificate).

Runs inside the certbot/certbot container (Python 3, standard library only, no third-party plugin):

    certbot certonly --manual --preferred-challenges dns \
      --manual-auth-hook    "/hooks/hostinger_dns_hook.py auth" \
      --manual-cleanup-hook "/hooks/hostinger_dns_hook.py cleanup" \
      --cert-name vetorsmart-wildcard -d vetorsmart.com.br -d '*.vetorsmart.com.br'

Certbot stores both hooks in the renewal configuration, so `certbot renew` repeats them without any manual step.

Official API (https://developers.hostinger.com, OpenAPI checked on 17/11/2026):
    GET    /api/dns/v1/zones/{domain}                         list the records of the zone
    PUT    /api/dns/v1/zones/{domain}  {overwrite, zone[]}    overwrite=false appends to the record set
    DELETE /api/dns/v1/zones/{domain}  {filters[{name,type}]} removes a record set

Environment:
    HOSTINGER_API_TOKEN_FILE  file holding only the API token (default /etc/letsencrypt/hostinger/token, mode 600)
    HOSTINGER_DNS_ZONE        zone managed at Hostinger (default vetorsmart.com.br)
    HOSTINGER_PROPAGATION     seconds to wait after publishing, before Let's Encrypt checks (default 90)
    CERTBOT_DOMAIN, CERTBOT_VALIDATION   set by Certbot

The token is never printed, logged or passed on the command line.
"""

import json
import os
import stat
import sys
import time
import urllib.error
import urllib.request

API = "https://developers.hostinger.com/api/dns/v1/zones/"
TTL = 300


def fail(message: str) -> None:
    print(f"hostinger-dns-hook: {message}", file=sys.stderr)
    sys.exit(1)


def token() -> str:
    path = os.environ.get("HOSTINGER_API_TOKEN_FILE", "/etc/letsencrypt/hostinger/token")
    try:
        mode = os.stat(path).st_mode
    except OSError:
        fail(f"arquivo do token não encontrado: {path}")
    if mode & (stat.S_IRWXG | stat.S_IRWXO):
        fail(f"o arquivo do token deve ter permissão 600: {path}")
    with open(path, encoding="utf-8") as handle:
        value = handle.read().strip()
    if not value:
        fail("arquivo do token vazio")
    return value


def call(method: str, zone: str, body=None):
    data = None if body is None else json.dumps(body).encode()
    request = urllib.request.Request(API + zone, data=data, method=method, headers={
        "Authorization": "Bearer " + token(),
        "Accept": "application/json",
        "Content-Type": "application/json",
        "User-Agent": "vetorsmart-certbot-hook/1.0",
    })
    for attempt in range(3):
        try:
            with urllib.request.urlopen(request, timeout=30) as response:
                payload = response.read()
                return json.loads(payload) if payload else None
        except urllib.error.HTTPError as error:
            if error.code == 429 and attempt < 2:
                time.sleep(int(error.headers.get("Retry-After", "10")))
                continue
            # The API message never contains the token; the request headers are not echoed.
            fail(f"{method} {zone}: HTTP {error.code} {error.read()[:300].decode(errors='replace')}")
        except urllib.error.URLError as error:
            fail(f"{method} {zone}: {error.reason}")
    fail(f"{method} {zone}: limite de requisições")


def record_name(domain: str, zone: str) -> str:
    """_acme-challenge for the apex (and its wildcard, which Certbot reports as the apex); deeper names keep the label."""
    if domain == zone:
        return "_acme-challenge"
    if not domain.endswith("." + zone):
        fail(f"{domain} não pertence à zona {zone}")
    return "_acme-challenge." + domain[: -len(zone) - 1]


def current_values(zone: str, name: str) -> list:
    for record_set in call("GET", zone) or []:
        if record_set.get("name") == name and record_set.get("type") == "TXT":
            return [r.get("content", "").strip('"') for r in record_set.get("records", [])]
    return []


def main() -> None:
    if len(sys.argv) != 2 or sys.argv[1] not in ("auth", "cleanup"):
        fail("uso: hostinger_dns_hook.py auth|cleanup")

    zone = os.environ.get("HOSTINGER_DNS_ZONE", "vetorsmart.com.br")
    domain = os.environ.get("CERTBOT_DOMAIN", "")
    value = os.environ.get("CERTBOT_VALIDATION", "")
    if not domain or not value:
        fail("CERTBOT_DOMAIN e CERTBOT_VALIDATION são definidos pelo Certbot")
    name = record_name(domain, zone)

    if sys.argv[1] == "auth":
        # Append: the apex and the wildcard need two values under the same name at the same time.
        call("PUT", zone, {"overwrite": False, "zone": [{"name": name, "type": "TXT", "ttl": TTL, "records": [{"content": value}]}]})
        if value not in current_values(zone, name):
            fail(f"registro TXT {name} não apareceu na zona após a publicação")
        time.sleep(int(os.environ.get("HOSTINGER_PROPAGATION", "90")))
        print(f"hostinger-dns-hook: TXT {name}.{zone} publicado")
        return

    # Cleanup: remove only this validation value and keep any other one still in use.
    remaining = [v for v in current_values(zone, name) if v != value]
    if remaining:
        call("PUT", zone, {"overwrite": True, "zone": [{"name": name, "type": "TXT", "ttl": TTL, "records": [{"content": v} for v in remaining]}]})
    else:
        call("DELETE", zone, {"filters": [{"name": name, "type": "TXT"}]})
    print(f"hostinger-dns-hook: TXT {name}.{zone} removido")


if __name__ == "__main__":
    main()
