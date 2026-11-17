# Mobile home content separation

The backend now exposes the two image sections on the mobile home screen independently.

## `GET /v1/home`

- `data.banners`: large slides shown below the profile header and above the shortcut menu.
- `data.offers`: smaller **Ofertas destacadas** cards shown below the shortcut menu.

Both arrays are already sorted by `displayOrder` ascending. Preserve the server order.

### Banner slide

```json
{
  "id": "banner_1",
  "imageUrl": "https://admin.askustours.com/uploads/slide.webp",
  "title": { "es": "", "en": "", "ar": "" },
  "subtitle": { "es": "", "en": "", "ar": "" },
  "linkUrl": null,
  "displayOrder": 10
}
```

### Featured offer

```json
{
  "id": "offer_1",
  "imageUrl": "https://admin.askustours.com/uploads/offer.webp",
  "title": { "es": "", "en": "", "ar": "" },
  "description": { "es": "", "en": "", "ar": "" },
  "highlights": [],
  "sectionHeading": null,
  "sections": [],
  "opensDrivers": false,
  "linkUrl": null,
  "displayOrder": 10
}
```

Do not reuse `data.offers` as the source for the large carousel. Use `data.banners` for the carousel and `data.offers` for the cards.

Administrators manage the collections separately through **Home slides** and **Home offers** in the dashboard.
