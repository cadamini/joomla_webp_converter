# System - Automatic WebP & Image Resizer for Joomla 4/5

This Joomla system plugin is designed to solve the "heavy image" problem automatically. It ensures that no matter how large an image a user uploads, the website visitor only receives a modern, compressed, and correctly sized version.

## 🚀 Key Features

* **Auto-Resize:** Detects images wider than **1280px** and scales them down to fit web standards while maintaining aspect ratio.
* **WebP Conversion:** Automatically generates a `.webp` version of any `.jpg`, `.jpeg`, or `.png` file found in the article content.
* **On-the-Fly Processing:** If a WebP version doesn't exist, the plugin creates it the moment the page is loaded.
* **Non-Destructive:** It never deletes your original images. It creates a companion `.webp` file, keeping your high-res originals safe.
* **Smart Caching:** Uses the Joomla Output Cache to store processed HTML, ensuring zero impact on server performance after the initial conversion.
* **Gallery Compatible:** Works perfectly with "Simple Image Gallery" and other content plugins by intercepting their output.

## 🛠 Installation

1. Download or create the `webpconverter.zip` containing `webpconverter.php` and `webpconverter.xml`.
2. In your Joomla Backend, go to **System → Install → Extensions**.
3. Upload the `.zip` file.
4. Navigate to **System → Plugins**, search for **"System - Automatic WebP & Image Resizer"** and set the status to **Enabled**.

## ⚙️ How it Works

The plugin follows a specific logic flow to ensure maximum speed and compatibility:

1. **Intercepts HTML:** Before the page is sent to the user, the plugin scans for `<img>` tags.
2. **Checks File System:** It looks for a `.webp` version of each image.
3. **Processes (if needed):**
* If no WebP exists, it loads the original.
* If the original is > 1280px wide, it resizes it.
* It saves the new file as a `.webp`.


4. **Rewrites URLs:** It changes `<img src="photo.jpg">` to `<img src="photo.webp">` in the code.
5. **Caches:** The final HTML is cached so the next visitor gets the result instantly.

## 📋 Requirements

* **Joomla:** 4.x or 5.x
* **PHP:** 7.4 or 8.x
* **PHP Extensions:** `GD Library` with WebP support (standard on most hosts).
* **Permissions:** Your `images/` folder must be writable (755).

## ⚠️ Important Notes

* **First Load:** The very first time you visit a page with many new images, it may take a few seconds to generate the files. Subsequent loads will be instant.
* **Media Manager:** You will see both the original file and a `.webp` version in your Media Manager. This is normal.
* **Transparency:** PNG transparency is preserved during the conversion process.
