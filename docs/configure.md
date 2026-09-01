# 4get configuation options

Welcome! This guide assumes that you have a working 4get instance. This will help you configure your instance to the best it can be!

> [!IMPORTANT]
> In the supported Security Search container, `data/config.php` is generated at
> every startup by `docker/gen_config.php`. Do not edit that generated file:
> the next restart will overwrite it. Set `FOURGET_*` values in
> `docker-compose.yml` or a reviewed private Compose environment/override, then
> recreate the container. Direct `data/config.php` edits below apply only to a
> bare-metal installation that does not run the container entrypoint. Keep
> proxy credentials and API keys outside Git.

# Files location
1. The generated/bare-metal configuration file is located at `data/config.php`
2. The proxies are located in `data/proxies/*.txt`
3. The captcha imagesets are located in `data/captcha/your_image_set/*.png`
4. The captcha font is located in `data/fonts/captcha.ttf`

# Cloudflare bypass (TLS check)
>These instructions have been updated to work with Debian 13 Trixie.

**Note: this only allows you to bypass the browser integrity checks. Captchas & javascript challenges will not be bypassed by this program!**

Configuring this lets you fetch images sitting behind Cloudflare and allows you to scrape the **Yep** search engine.

To come up with this set of instructions, I used [this guide](https://github.com/lwthiker/curl-impersonate/blob/main/INSTALL.md#native-build) as a reference, but trust me you probably want to stick to what's written on this page.

First, compile curl-impersonate (the firefox flavor).
```sh
git clone https://github.com/lwthiker/curl-impersonate/
cd curl-impersonate
sudo apt install build-essential pkg-config cmake ninja-build curl autoconf automake libtool python3-pip libnss3 libnss3-dev
mkdir build
cd build
../configure
make firefox-build
sudo make firefox-install
sudo ldconfig
```

Now, after compiling, you should have a `libcurl-impersonate-ff.so` sitting somewhere. Mine is located at `/usr/local/lib/libcurl-impersonate-ff.so`. Patch your PHP install so that it loads the right library:

```sh
sudo systemctl edit php8.4-fpm.service
```

This opens a text editor. Add the following content between the two reference comments:

```sh
### Editing /etc/systemd/system/php8.4-fpm.service.d/override.conf
### Anything between here and the comment below will become the contents of the>

[Service]
Environment="LD_PRELOAD=/usr/local/lib/libcurl-impersonate-ff.so"
Environment="CURL_IMPERSONATE=firefox117"

### Edits below this comment will be discarded
```

Restart php8.4-fpm (`sudo service php8.4-fpm restart`). To test the setup, run a search with the Yep provider, which verifies TLS. A result response confirms the configuration; an upstream timeout should be investigated separately.

# Robots.txt
Make sure you configure this right to optimize your search engine presence! Head over to `/robots.txt` and change the 4get.ca domain to your own domain.

# Server listing
To be listed on https://4get.ca/instances , you must contact *any* of the people in the server list and ask them to add you to their list of instances in their configuration. The instance list is distributed, and I don't have control over it.

If you see spammy entries in your instances list, simply remove the instance from your list that pushes the offending entries.

# Proxies
4get supports rotating proxies for scrapers! Configuring one is really easy.

1. Head over to the **proxies** folder. Give it any name you want, like `myproxy`, but make sure it has the `txt` extension.
2. Add your proxies to the file. Examples:
	```conf
	# format -> <protocol>:<address>:<port>:<username>:<password>
	# protocol list:
	# raw_ip, http, https, socks4, socks5, socks4a, socks5_hostname
	socks5:1.1.1.1:proxy_user:proxy_password
	http:1.3.3.7::
	raw_ip::::
	```
3. Go to the **main configuration file**. Then, find which website you want to setup a proxy for.
4. Modify the value `false` with `"myproxy"`, with quotes included and the semicolon at the end.

Done! The scraper you chose should now be using the rotating proxies. When asking for the next page of results, it will use the same proxy to avoid detection!

## Important!
If you ever test out a `socks5` proxy locally on your machine and find out it works but doesn't on your server, try supplying the `socks5_hostname` protocol instead. Hopefully this tip can save you 3 hours of your life!
