# GitHub Updater (Lite)
Enable automatic updates for your GitHub-hosted WordPress plugins.

This is a variant of Andy Fragen's excellent [GitHub Updater](https://github.com/afragen/github-updater) plugin. If you need all the bells and whistles (BitBucket, private repositories, etc), please use Andy's plugin!

## Usage

Add the following line to your plugin's meta information, replacing `owner/repo` with your actual repository.

```
GitHub URI: owner/repo
```

Then, add `github-updater.php` to your plugin folder, and `include()` it from within your main plugin file.

```php
include( dirname( __FILE__ ) . '/github-updater.php' );
```

That's it, have fun!
