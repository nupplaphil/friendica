---
title: post-category
tags:
  - database
  - table
  - developer
---
# Table post-category

post relation to categories

## Fields

| Field  | Description                                               | Type               | Null | Key | Default | Extra |
| ------ | --------------------------------------------------------- | ------------------ | ---- | --- | ------- | ----- |
| uri-id | Id of the item-uri table entry that contains the item uri | int unsigned       | NO   | PRI | NULL    |       |
| uid    | User id                                                   | mediumint unsigned | NO   | PRI | 0       |       |
| type   |                                                           | tinyint unsigned   | NO   | PRI | 0       |       |
| tid    |                                                           | int unsigned       | NO   | PRI | 0       |       |

## Indexes

| Name       | Fields                 |
| ---------- | ---------------------- |
| PRIMARY    | uri-id, uid, type, tid |
| tid        | tid                    |
| uid_uri-id | uid, uri-id            |

## Foreign Keys

| Field  | Target Table                           | Target Field |
| ------ | -------------------------------------- | ------------ |
| uri-id | [item-uri](/spec/database/db_item-uri) | id           |
| uid    | [user](/spec/database/db_user)         | uid          |
| tid    | [tag](/spec/database/db_tag)           | id           |

Return to [database documentation](/spec/database/)
