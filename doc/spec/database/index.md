# Database Tables

| Table                                                                    | Comment                                                                  |
| ------------------------------------------------------------------------ | ------------------------------------------------------------------------ |
| [2fa_app_specific_password](/spec/database/db_2fa_app_specific_password) | Two-factor app-specific _password                                        |
| [2fa_recovery_codes](/spec/database/db_2fa_recovery_codes)               | Two-factor authentication recovery codes                                 |
| [2fa_trusted_browser](/spec/database/db_2fa_trusted_browser)             | Two-factor authentication trusted browsers                               |
| [addon](/spec/database/db_addon)                                         | registered addons                                                        |
| [apcontact](/spec/database/db_apcontact)                                 | ActivityPub compatible contacts - used in the ActivityPub implementation |
| [application-marker](/spec/database/db_application-marker)               | Timeline marker                                                          |
| [application-token](/spec/database/db_application-token)                 | OAuth user token                                                         |
| [application](/spec/database/db_application)                             | OAuth application                                                        |
| [attach](/spec/database/db_attach)                                       | file attachments                                                         |
| [cache](/spec/database/db_cache)                                         | Stores temporary data                                                    |
| [config](/spec/database/db_config)                                       | main configuration storage                                               |
| [contact-relation](/spec/database/db_contact-relation)                   | Contact relations                                                        |
| [contact](/spec/database/db_contact)                                     | contact table                                                            |
| [conv](/spec/database/db_conv)                                           | private messages                                                         |
| [conversation](/spec/database/db_conversation)                           | Raw data and structure information for messages                          |
| [delayed-post](/spec/database/db_delayed-post)                           | Posts that are about to be distributed at a later time                   |
| [diaspora-interaction](/spec/database/db_diaspora-interaction)           | Signed Diaspora Interaction                                              |
| [endpoint](/spec/database/db_endpoint)                                   | ActivityPub endpoints - used in the ActivityPub implementation           |
| [event](/spec/database/db_event)                                         | Events                                                                   |
| [fcontact](/spec/database/db_fcontact)                                   | Diaspora compatible contacts - used in the Diaspora implementation       |
| [fsuggest](/spec/database/db_fsuggest)                                   | friend suggestion stuff                                                  |
| [group](/spec/database/db_group)                                         | privacy groups, group info                                               |
| [group_member](/spec/database/db_group_member)                           | privacy groups, member info                                              |
| [gserver-tag](/spec/database/db_gserver-tag)                             | Tags that the server has subscribed                                      |
| [gserver](/spec/database/db_gserver)                                     | Global servers                                                           |
| [hook](/spec/database/db_hook)                                           | addon hook registry                                                      |
| [inbox-status](/spec/database/db_inbox-status)                           | Status of ActivityPub inboxes                                            |
| [intro](/spec/database/db_intro)                                         |                                                                          |
| [item-uri](/spec/database/db_item-uri)                                   | URI and GUID for items                                                   |
| [locks](/spec/database/db_locks)                                         |                                                                          |
| [mail](/spec/database/db_mail)                                           | private messages                                                         |
| [mailacct](/spec/database/db_mailacct)                                   | Mail account data for fetching mails                                     |
| [manage](/spec/database/db_manage)                                       | table of accounts that can manage each other                             |
| [notification](/spec/database/db_notification)                           | notifications                                                            |
| [notify-threads](/spec/database/db_notify-threads)                       |                                                                          |
| [notify](/spec/database/db_notify)                                       | [Deprecated] User notifications                                          |
| [oembed](/spec/database/db_oembed)                                       | cache for OEmbed queries                                                 |
| [openwebauth-token](/spec/database/db_openwebauth-token)                 | Store OpenWebAuth token to verify contacts                               |
| [parsed_url](/spec/database/db_parsed_url)                               | cache for 'parse_url' queries                                            |
| [pconfig](/spec/database/db_pconfig)                                     | personal (per user) configuration storage                                |
| [permissionset](/spec/database/db_permissionset)                         |                                                                          |
| [photo](/spec/database/db_photo)                                         | photo storage                                                            |
| [post-category](/spec/database/db_post-category)                         | post relation to categories                                              |
| [post-collection](/spec/database/db_post-collection)                     | Collection of posts                                                      |
| [post-content](/spec/database/db_post-content)                           | Content for all posts                                                    |
| [post-delivery-data](/spec/database/db_post-delivery-data)               | Delivery data for items                                                  |
| [post-delivery](/spec/database/db_post-delivery)                         | Delivery data for posts for the batch processing                         |
| [post-history](/spec/database/db_post-history)                           | Post history                                                             |
| [post-link](/spec/database/db_post-link)                                 | Post related external links                                              |
| [post-media](/spec/database/db_post-media)                               | Attached media                                                           |
| [post-question-option](/spec/database/db_post-question-option)           | Question option                                                          |
| [post-question](/spec/database/db_post-question)                         | Question                                                                 |
| [post-tag](/spec/database/db_post-tag)                                   | post relation to tags                                                    |
| [post-thread-user](/spec/database/db_post-thread-user)                   | Thread related data per user                                             |
| [post-thread](/spec/database/db_post-thread)                             | Thread related data                                                      |
| [post-user-notification](/spec/database/db_post-user-notification)       | User post notifications                                                  |
| [post-user](/spec/database/db_post-user)                                 | User specific post data                                                  |
| [post](/spec/database/db_post)                                           | Structure for all posts                                                  |
| [process](/spec/database/db_process)                                     | Currently running system processes                                       |
| [profile](/spec/database/db_profile)                                     | user profiles data                                                       |
| [profile_field](/spec/database/db_profile_field)                         | Custom profile fields                                                    |
| [push_subscriber](/spec/database/db_push_subscriber)                     | Used for OStatus: Contains feed subscribers                              |
| [register](/spec/database/db_register)                                   | registrations requiring admin approval                                   |
| [search](/spec/database/db_search)                                       |                                                                          |
| [session](/spec/database/db_session)                                     | web session storage                                                      |
| [storage](/spec/database/db_storage)                                     | Data stored by Database storage backend                                  |
| [subscription](/spec/database/db_subscription)                           | Push Subscription for the API                                            |
| [tag](/spec/database/db_tag)                                             | tags and mentions                                                        |
| [user-contact](/spec/database/db_user-contact)                           | User specific public contact data                                        |
| [user](/spec/database/db_user)                                           | The local users                                                          |
| [userd](/spec/database/db_userd)                                         | Deleted usernames                                                        |
| [verb](/spec/database/db_verb)                                           | Activity Verbs                                                           |
| [worker-ipc](/spec/database/db_worker-ipc)                               | Inter process communication between the frontend and the worker          |
| [workerqueue](/spec/database/db_workerqueue)                             | Background tasks queue entries                                           |
