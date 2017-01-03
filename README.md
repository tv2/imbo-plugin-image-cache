# Image-Cache Imbo plugin

Setup a cache of a storage-backend by using another storage-backend. Useful if you primary storage is a slow but cheap option, but you want a fast but expensive backend to serve as a cache of the most recently used images.

The original use-case was to use S3 for storage, but have a on-disk version of the images used in the last two weeks.


## Installation

### Setting up the dependencies

If you've installed Imbo through composer, getting the upstream plugin up and running is easy. Just add `tv2/imbo-plugin-image-cache` as a dependency and run `composer update`.

```json
{
    "require": {
        "tv2/imbo-plugin-image-cache": "dev-master",
    }
}
```

### Configuring imbo

Once you've got the plugin installed, you need to configure your upstream. An example configuration can be found in `config/config.php.dist`. If you copy the file to your configuration-directory, rename it to something like `image-cache.php` and adjust the parameters, you should be good to go.

## License

Copyright (c) 2016, TV 2 Danmark A/S

Licensed under the MIT license.
