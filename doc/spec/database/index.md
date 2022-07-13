# Database Tables

| Table                                                          | Comment                                                                  |
| -------------------------------------------------------------- | ------------------------------------------------------------------------ |
| [2fa_app_specific_password](./db_2fa_app_specific_password.md) | Two-factor app-specific _password                                        |
| [2fa_recovery_codes](./db_2fa_recovery_codes.md)               | Two-factor authentication recovery codes                                 |
| [2fa_trusted_browser](./db_2fa_trusted_browser.md)             | Two-factor authentication trusted browsers                               |
| [addon](./db_addon.md)                                         | registered addons                                                        |
| [apcontact](./db_apcontact.md)                                 | ActivityPub compatible contacts - used in the ActivityPub implementation |
| [application-marker](./db_application-marker.md)               | Timeline marker                                                          |
| [application-token](./db_application-token.md)                 | OAuth user token                                                         |
| [application](./db_application.md)                             | OAuth application                                                        |
| [attach](./db_attach.md)                                       | file attachments                                                         |
| [cache](./db_cache.md)                                         | Stores temporary data                                                    |
| [config](./db_config.md)                                       | main configuration storage                                               |
| [contact-relation](./db_contact-relation.md)                   | Contact relations                                                        |
| [contact](./db_contact.md)                                     | contact table                                                            |
| [conv](./db_conv.md)                                           | private messages                                                         |
| [conversation](./db_conversation.md)                           | Raw data and structure information for messages                          |
| [delayed-post](./db_delayed-post.md)                           | Posts that are about to be distributed at a later time                   |
| [diaspora-interaction](./db_diaspora-interaction.md)           | Signed Diaspora Interaction                                              |
| [endpoint](./db_endpoint.md)                                   | ActivityPub endpoints - used in the ActivityPub implementation           |
| [event](./db_event.md)                                         | Events                                                                   |
| [fcontact](./db_fcontact.md)                                   | Diaspora compatible contacts - used in the Diaspora implementation       |
| [fsuggest](./db_fsuggest.md)                                   | friend suggestion stuff                                                  |
| [group](./db_group.md)                                         | privacy groups, group info                                               |
| [group_member](./db_group_member.md)                           | privacy groups, member info                                              |
| [gserver-tag](./db_gserver-tag.md)                             | Tags that the server has subscribed                                      |
| [gserver](./db_gserver.md)                                     | Global servers                                                           |
| [hook](./db_hook.md)                                           | addon hook registry                                                      |
| [inbox-status](./db_inbox-status.md)                           | Status of ActivityPub inboxes                                            |
| [intro](./db_intro.md)                                         |                                                                          |
| [item-uri](./db_item-uri.md)                                   | URI and GUID for items                                                   |
| [locks](./db_locks.md)                                         |                                                                          |
| [mail](./db_mail.md)                                           | private messages                                                         |
| [mailacct](./db_mailacct.md)                                   | Mail account data for fetching mails                                     |
| [manage](./db_manage.md)                                       | table of accounts that can manage each other                             |
| [notification](./db_notification.md)                           | notifications                                                            |
| [notify-threads](./db_notify-threads.md)                       |                                                                          |
| [notify](./db_notify.md)                                       | [Deprecated] User notifications                                          |
| [oembed](./db_oembed.md)                                       | cache for OEmbed queries                                                 |
| [openwebauth-token](./db_openwebauth-token.md)                 | Store OpenWebAuth token to verify contacts                               |
| [parsed_url](./db_parsed_url.md)                               | cache for 'parse_url' queries                                            |
| [pconfig](./db_pconfig.md)                                     | personal (per user) configuration storage                                |
| [permissionset](./db_permissionset.md)                         |                                                                          |
| [photo](./db_photo.md)                                         | photo storage                                                            |
| [post-category](./db_post-category.md)                         | post relation to categories                                              |
| [post-collection](./db_post-collection.md)                     | Collection of posts                                                      |
| [post-content](./db_post-content.md)                           | Content for all posts                                                    |
| [post-delivery-data](./db_post-delivery-data.md)               | Delivery data for items                                                  |
| [post-delivery](./db_post-delivery.md)                         | Delivery data for posts for the batch processing                         |
| [post-history](./db_post-history.md)                           | Post history                                                             |
| [post-link](./db_post-link.md)                                 | Post related external links                                              |
| [post-media](./db_post-media.md)                               | Attached media                                                           |
| [post-question-option](./db_post-question-option.md)           | Question option                                                          |
| [post-question](./db_post-question.md)                         | Question                                                                 |
| [post-tag](./db_post-tag.md)                                   | post relation to tags                                                    |
| [post-thread-user](./db_post-thread-user.md)                   | Thread related data per user                                             |
| [post-thread](./db_post-thread.md)                             | Thread related data                                                      |
| [post-user-notification](./db_post-user-notification.md)       | User post notifications                                                  |
| [post-user](./db_post-user.md)                                 | User specific post data                                                  |
| [post](./db_post.md)                                           | Structure for all posts                                                  |
| [process](./db_process.md)                                     | Currently running system processes                                       |
| [profile](./db_profile.md)                                     | user profiles data                                                       |
| [profile_field](./db_profile_field.md)                         | Custom profile fields                                                    |
| [push_subscriber](./db_push_subscriber.md)                     | Used for OStatus: Contains feed subscribers                              |
| [register](./db_register.md)                                   | registrations requiring admin approval                                   |
| [search](./db_search.md)                                       |                                                                          |
| [session](./db_session.md)                                     | web session storage                                                      |
| [storage](./db_storage.md)                                     | Data stored by Database storage backend                                  |
| [subscription](./db_subscription.md)                           | Push Subscription for the API                                            |
| [tag](./db_tag.md)                                             | tags and mentions                                                        |
| [user-contact](./db_user-contact.md)                           | User specific public contact data                                        |
| [user](./db_user.md)                                           | The local users                                                          |
| [userd](./db_userd.md)                                         | Deleted usernames                                                        |
| [verb](./db_verb.md)                                           | Activity Verbs                                                           |
| [worker-ipc](./db_worker-ipc.md)                               | Inter process communication between the frontend and the worker          |
| [workerqueue](./db_workerqueue.md)                             | Background tasks queue entries                                           |
