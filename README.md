# IPCow

IPCow hosts a small set of online utilities for inspecting your network connection. The
site is built with [Jekyll](https://jekyllrb.com/) and provides pages to view your
IP information, perform DNS lookups and more.

## Running locally

Use the provided setup script to install Bundler, Jekyll and the required gems.
Then start a local development server:

```bash
./setup.sh
bundle exec jekyll serve
```

Once the server is running, open <http://localhost:4000> in your browser.

## API endpoints

Several PHP scripts are included for dynamic information:

- `api/info.php` – returns JSON describing your connection (IPv4, IPv6, hostname and geolocation).
- `api/dns-checker.php` – checks DNS records for a supplied domain name.

These endpoints are referenced by the site pages and may also be queried directly.

## Production site

The live version of IPCow is available at [https://prod.ipcow.com](https://prod.ipcow.com).
