---
title: Import / Export Contacts
tags:
- user
---
# Import / Export of followed Contacts

In addition to [move your account](./move-account.md) you can export and import the list of accounts you follow.
The exported list is stored as CSV file that is compatible to the format used by other platforms as e.g. Mastodon, Misskey or Pleroma.

## Export of followed Contacts

To export the list of accounts that you follow, go to the [Settings Export personal date] (`https://your-site.info/settings/userexport`) and click the [Export Contacts to CSV] (`https://your-site.info/settings/userexport/contact`).

## Import of followed Contacts

To import contacts from a CSV file, go to the Settings page.
At the bottom of the *account settings* page you'll find the *import contacts* section.
Upload the CSV file there.

### Supported File Format

The CSV file *must* contain at least one column.
In the first column the table should contain either the handle or URL of a followed account.
(one account per row.)
Other columns in the CSV file will be ignored.
