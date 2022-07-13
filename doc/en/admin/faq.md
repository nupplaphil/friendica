---
title: FAQ
tags:
  - admin
  - faq
---
# Frequently Asked Questions (Admin) - FAQ

## Can I configure multiple domains with the same code instance?

No, this function is no longer supported as of Friendica 3.3 onwards.

## Where can I find the source code of friendica, addons and themes?

You can find the main repository [here](https://github.com/friendica/friendica).
There you will always find the current stable version of friendica.

Addons are listed at [this page](https://github.com/friendica/friendica-addons).

If you are searching for new themes, you can find them at [github.com/bkil/friendica-themes](https://github.com/bkil/friendica-themes)

## I've changed my email address now the admin panel is gone?

Have a look into your <tt>config/local.config.php</tt> and fix your email address there.

## Can there be more than one admin for a node?

Yes.
You just have to list more than one email address in the `config/local.config.php` file.
The listed emails need to be separated by a comma.

## The Database structure seems not to be updated. What can I do?

Please have a look at the Admin panel under DB updates (`/admin/dbsync/`) and follow the link to *check database structure*.
This will start a background process to check if the structure is up to the current definition.

You can manually execute the structure update from the CLI in the base directory of your Friendica installation by running the following command:

```sh
bin/console dbstructure update
```

if there occur any errors, please contact the [support forum](https://forum.friendi.ca/profile/helpers).