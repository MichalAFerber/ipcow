# IP Cow 🐮

A modern, privacy-focused web application that displays your IP address, geolocation, ISP information, and device details with a beautiful glass-morphism design.

![IP Cow Screenshot](https://img.shields.io/badge/Status-Live-brightgreen) ![License](https://img.shields.io/badge/License-MIT-blue)

## 🌟 Features

- **IP Address Detection**: Shows both IPv4 and IPv6 addresses
- **Geolocation Information**: Approximate location based on IP geolocation
- **ISP Details**: Internet Service Provider information
- **Device & Browser Info**: Comprehensive browser and device specifications
- **Interactive Map**: Visual representation of your location using Leaflet
- **Dark/Light Mode**: Beautiful glass-morphism theme toggle
- **Responsive Design**: Works perfectly on desktop, tablet, and mobile
- **Privacy-Focused**: No tracking, no data collection, client-side only
- **Export Functionality**: Download your information as JSON

## 🚀 How It Works

IP Cow is a client-side web application that:

1. **Fetches IP Addresses**: Uses the ipify API to get your public IPv4 and IPv6 addresses
2. **Geolocates Your Position**: Calls IPStack API for location data (approximate, based on IP)
3. **Analyzes Your Device**: Uses Bowser.js to detect browser, OS, and device information
4. **Displays Results**: Presents all information in a clean, organized interface
5. **Interactive Elements**: Click IP addresses to copy, hover for tooltips, toggle themes

All data processing happens in your browser - no server-side tracking or data storage.

## 🛠️ Tech Stack

### Frontend
- **HTML5**: Semantic markup with accessibility features
- **CSS3**: Custom properties, glass-morphism effects, responsive design
- **JavaScript (ES6+)**: Async/await, DOM manipulation, event handling

### Libraries & APIs
- **Leaflet**: Interactive maps with OpenStreetMap tiles
- **Bowser**: Browser and device detection
- **ipify**: Public IP address detection
- **IPStack**: Geolocation and ISP information

### Development Tools
- **Jekyll**: Static site generator for maintainable includes and layouts
- **Ruby/Bundler**: Jekyll dependency management
- **Node.js**: Additional package management
- **Git**: Version control
- **VS Code**: Development environment

## 📱 Screenshots

*Add screenshots here when available*

## 🏃‍♂️ Running Locally

### Prerequisites
- Ruby (v2.5 or higher) with Bundler
- Jekyll
- Node.js (v14 or higher) - optional for additional tooling
- npm or yarn - optional

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/ipcow.com.git
   cd ipcow.com
   ```

2. **Install Jekyll and Bundler** (if not already installed)
   ```bash
   gem install jekyll bundler
   ```

3. **Install dependencies** (optional Node.js packages)
   ```bash
   npm install
   ```

4. **Start the Jekyll development server**
   ```bash
   npm run serve
   # or directly with Jekyll:
   # jekyll serve
   ```

5. **Open your browser**
   ```
   http://localhost:4000
   ```

### Build for Production

```bash
npm run build
# or directly with Jekyll:
# jekyll build
```

The built site will be in the `_site/` directory.

## 🤝 Contributing

Contributions are welcome! This project aims to be a helpful tool for developers and regular users alike.

### How to Contribute

1. **Fork the repository**
2. **Create a feature branch**
   ```bash
   git checkout -b feature/amazing-feature
   ```
3. **Make your changes**
4. **Test thoroughly**
5. **Commit your changes**
   ```bash
   git commit -m 'Add amazing feature'
   ```
6. **Push to the branch**
   ```bash
   git push origin feature/amazing-feature
   ```
7. **Open a Pull Request**

### Development Guidelines

- Follow existing code style and conventions
- Test on multiple browsers and devices
- Ensure responsive design works on all screen sizes
- Keep the codebase clean and well-documented
- Respect user privacy - no tracking or data collection
- **Header/Footer Changes**: Update `_includes/header.html` and `_includes/footer.html` for site-wide changes
- **Layout Changes**: Modify `_layouts/default.html` for page structure changes
- **New Pages**: Add Markdown files to `_pages/` directory with proper front matter

### Areas for Contribution

- **UI/UX Improvements**: Better design, animations, accessibility
- **New Features**: Additional device info, network diagnostics
- **Performance**: Code optimization, lazy loading
- **Internationalization**: Multi-language support
- **Testing**: Unit tests, integration tests

## 📄 API Usage

This project uses free tiers of the following APIs:
- **ipify**: https://ipify.org/ (unlimited free requests)
- **IPStack**: https://ipstack.com/ (10,000 free requests/month)
- **OpenStreetMap**: https://www.openstreetmap.org/ (free to use)

## 📋 Site Files

- **ads.txt** - Google AdSense verification
- **robots.txt** - Search engine crawling instructions
- **sitemap.xml** - Search engine indexing
- **humans.txt** - Site credits and information
- **security.txt** - Security contact information (RFC 9116)
- **.well-known/** - Standard web discovery files
- **.htaccess** - Apache server configuration

## 🔒 Privacy & Security

- **No Data Collection**: All processing happens client-side
- **No Tracking**: No analytics, cookies, or fingerprinting
- **API Keys**: Free tier APIs with reasonable rate limits
- **HTTPS Only**: All external requests use secure connections

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **ipify** for reliable IP detection
- **IPStack** for geolocation services
- **Leaflet** for the amazing mapping library
- **Bowser** for browser detection
- **OpenStreetMap** contributors for map data

## 📞 Contact

- **Author**: Michal Ferber
- **Website**: https://ipcow.com
- **Email**: michal@techguywithabeard.com

---

**IP Cow** - Know your digital footprint 🐮✨
