# WP Data Sanitizer 🧼

Keep your staging environment squeaky clean with WP Data Sanitizer – the ultimate solution for WordPress developers and site administrators who need to work with real data without compromising sensitive information.

## 🚀 Features

- **Selective Sanitization**: Choose what to sanitize – emails, usernames, post content, or comments.
- **Role-Based Exclusions**: Automatically skip sanitization for important user roles.
- **Batch Processing**: Handle large datasets with ease through efficient batch operations.
- **User-Friendly Interface**: Intuitive admin panel seamlessly integrated into WordPress.

## 🛠 Installation

1. Download the plugin zip file.
2. Navigate to your WordPress admin panel.
3. Go to Plugins > Add New > Upload Plugin.
4. Choose the downloaded zip file and click "Install Now".
5. After installation, click "Activate Plugin".

## 📊 Usage

1. In your WordPress admin panel, navigate to Tools > Data Sanitizer.
2. Select the data types you want to sanitize.
3. Click "Save Settings" to store your preferences.
4. Hit "Start Sanitization" to begin the process.
5. Monitor the progress bar and check the logs for details.

## ⚠️ Important Notes

- **Always backup your database before running any sanitization process.**
- This plugin modifies your database. Use with caution, especially in production environments.
- Sanitized data is irreversible. Make sure you really want to modify your data before proceeding.

## 🛡 Security

WP Data Sanitizer takes security seriously:

- Only users with `manage_options` capability can access the sanitization features.
- All actions are protected with WordPress nonces to prevent CSRF attacks.
- Sanitization processes are run in batches to prevent timeouts on large datasets.

## 🤝 Contributing

We welcome contributions! Here's how you can help:

1. Fork the repository.
2. Create a new branch for your feature: `git checkout -b feature/AmazingFeature`
3. Commit your changes: `git commit -m 'Add some AmazingFeature'`
4. Push to the branch: `git push origin feature/AmazingFeature`
5. Open a pull request.

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

## 📜 License

This project is licensed under the GPL-2.0 License - see the [LICENSE](LICENSE) file for details.

---

Made with ❤️ by Open WP Club

Got questions or feedback? [Open an issue](https://github.com/openwpclub/wp-sanitize/issues) - we'd love to hear from you!
