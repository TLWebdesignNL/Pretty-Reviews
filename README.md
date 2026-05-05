# Pretty Reviews

Pretty Reviews is a Joomla site module that displays Google business reviews in a Bootstrap carousel. It fetches reviews from the Google Places API, stores them in a local JSON cache, and lets you choose how reviews are shown on the frontend.

## Features

- Display Google rating, review count, review text, author names, profile photos, star ratings, and a "View all reviews" link.
- Fetch reviews manually from the Joomla module edit screen.
- Optional scheduled refreshes through the companion task plugin.
- Keep Google API credentials server-side; the browser only sends the module ID and Joomla CSRF token.
- Sort fetched reviews by Google's "most relevant" or "newest" mode.
- Display reviews by newest first or random order.
- Limit the number of displayed reviews.
- Hide reviews without text.
- Multilingual language files: English, Dutch, and German.

## Requirements

- Joomla 4, 5, or 6.
- PHP version supported by your Joomla installation.
- A Google Maps Platform API key with access to the Places API.
- A Google Place ID for the business you want to show reviews for.

## Installation

1. Download the latest release ZIP from the GitHub releases page.
2. In Joomla Administrator, go to **System** -> **Install** -> **Extensions**.
3. Upload and install the ZIP.
4. Go to **Content** -> **Site Modules**.
5. Create or edit a **Pretty Reviews** module.
6. Configure the Google Place ID and API key.
7. Save the module, then click **Update Reviews** in the toolbar.

Latest release:
https://github.com/TLWebdesignNL/Pretty-Reviews/releases

## Google API Key Setup

Pretty Reviews 1.2.0 and newer fetches Google reviews server-side. That means Google sees the request as coming from your server, not from the visitor's browser.

Recommended key restrictions:

- **Application restriction:** IP addresses.
- Add the public server IP address of the website.
- **API restriction:** restrict the key to the required Google Places API.

Do not use HTTP referrer-only restrictions for this module's server-side refresh. Google will reject the request with a message similar to:

```text
REQUEST_DENIED: This IP, site or mobile application is not authorized to use this API key.
```

## Configuration

### Google Place ID

The Place ID of the Google Business Profile location. Google documents how to find it here:
https://developers.google.com/maps/documentation/places/web-service/place-id

### Google API Key

The API key used for the Google Places request. Keep this key restricted to the website server IP and the required Places API.

### Reviews Fetch Sort

Controls how Google sorts the reviews returned by the API:

- **Most relevant**
- **Newest**

Google returns a limited review set. This option controls the upstream fetch, not only frontend display.

### Limit Reviews

Maximum number of cached reviews to display on the frontend. Leave empty or set to `0` to show all cached reviews.

### Reviews Display Sort

Controls how cached reviews are displayed:

- **Newest**
- **Random**

### Hide Empty Reviews

When enabled, reviews without text are hidden on the frontend.

## Manual Refresh

Open the module edit screen in Joomla Administrator and click **Update Reviews**.

The refresh action:

- requires the Joomla form token;
- requires edit permission for the module;
- reads the Google credentials from the saved module parameters;
- writes the raw review cache to `media/mod_prettyreviews/data-{module_id}.json`;
- returns clear feedback when Google rejects the API request.

Save the module before the first refresh so Joomla has a module ID and stored credentials to use.

## Scheduled Refreshes

Automatic updates are handled by the companion task plugin:

https://github.com/TLWebdesignNL/Pretty-Reviews-Task-Scheduler-Plugin

Use version 1.1.0 or newer of the task plugin with Pretty Reviews module 1.2.0 or newer. The task plugin calls the module helper directly and no longer sends credentials through an HTTP request.

## Troubleshooting

### The JSON cache file is empty

Update to Pretty Reviews 1.2.0 or newer. Older refresh logic could treat an invalid Google response as a successful write. Current versions reject invalid Google responses and keep the error visible.

### Google returns `REQUEST_DENIED`

Check the Google API key restrictions. Because refreshes are server-side, the key must allow the website server IP address. HTTP referrer-only restrictions will not work.

### "Google Place ID and API key must be configured"

Fill in both fields, save the module, and then run **Update Reviews**.

### "Pretty Reviews 1.2.0 or newer is required" in the task scheduler

Update this module before using the task plugin. The task plugin depends on the helper API introduced in Pretty Reviews 1.2.0.

### Reviews do not appear on the frontend

Check:

- the module is published;
- the module is assigned to the correct menu items;
- the selected template position exists;
- the JSON cache file contains reviews;
- your display limit and "hide empty reviews" settings do not filter every review.

## Security Notes

Pretty Reviews 1.2.0 removed the old URL-secret refresh flow. The browser no longer receives the Google API key, Place ID, review sort value, or static secret in request URLs or `data-*` attributes.

The backend refresh now uses:

- Joomla CSRF token validation;
- per-module `core.edit` permission checks;
- server-side credential lookup from the module record;
- Joomla's HTTP client for Google API requests;
- escaped review output in the frontend layout.

## Releases and Updates

The module includes a Joomla update server:

```text
https://raw.githubusercontent.com/TLWebdesignNL/Pretty-Reviews/main/updates.xml
```

Release changelog:

```text
https://raw.githubusercontent.com/TLWebdesignNL/Pretty-Reviews/main/changelog.xml
```

## Links

- GitHub: https://github.com/TLWebdesignNL/Pretty-Reviews
- Releases: https://github.com/TLWebdesignNL/Pretty-Reviews/releases
- Issues: https://github.com/TLWebdesignNL/Pretty-Reviews/issues
- Joomla Extensions Directory: https://extensions.joomla.org/extension/pretty-reviews/
- TLWebdesign: https://tlwebdesign.nl/

## License

GNU General Public License version 2 or later.
