# LocalBusiness Schema Pro

WordPress plugin for service-area operators — charter captains, tour operators, HVAC/plumbing/electricians, mobile mechanics, guides. Emits proper LocalBusiness JSON-LD with multi-location support, trip/service catalog with seasonal availability, and coexistence detection so it sits on top of Yoast/Rank Math without conflict.

**Product page:** https://kitmobley.com/plugins/localbusiness-schema-pro/

## Positioning

Rank Math and Yoast Local handle one storefront well. This is for the operator with a fleet, a route, or a service area — where those plugins stop.

## Free vs Pro

| Tier | Locations | Services / Trips | Coexistence detection | Pro admin gates |
| --- | --- | --- | --- | --- |
| Free | 1 | — | ✓ | — |
| Solo Pro ($99/yr, 1 site) | 5 | ✓ | ✓ | ✓ |
| Agency Pro ($249/yr, 25 sites) | 25 | ✓ | ✓ | ✓ |

## v1.1 roadmap

- Polygon service-area drawer (Leaflet) → GeoShape `areaServed`
- CSV bulk import for multi-location clients

## Build

```sh
./build.sh
# → dist/localbusiness-schema-pro-<version>.zip
```

## License

GPL v2 or later.
