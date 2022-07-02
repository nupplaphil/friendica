---
title: post-user-notification
tags:
  - database
  - table
  - developer
---
# Table post-user-notification

User post notifications

## Fields

| Field             | Description                                               | Type               | Null | Key | Default | Extra |
| ----------------- | --------------------------------------------------------- | ------------------ | ---- | --- | ------- | ----- |
| uri-id            | Id of the item-uri table entry that contains the item uri | int unsigned       | NO   | PRI | NULL    |       |
| uid               | Owner id which owns this copy of the item                 | mediumint unsigned | NO   | PRI | NULL    |       |
| notification-type |                                                           | smallint unsigned  | NO   |     | 0       |       |

## Indexes

| Name    | Fields      |
| ------- | ----------- |
| PRIMARY | uid, uri-id |
| uri-id  | uri-id      |

## Foreign Keys

| Field  | Target Table                           | Target Field |
| ------ | -------------------------------------- | ------------ |
| uri-id | [item-uri](/spec/database/db_item-uri) | id           |
| uid    | [user](/spec/database/db_user)         | uid          |

Return to [database documentation](/spec/database/)
