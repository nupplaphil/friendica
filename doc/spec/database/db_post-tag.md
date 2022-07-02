---
title: post-tag
tags:
  - database
  - table
  - developer
---
# Table post-tag

post relation to tags

## Fields

| Field  | Description                                               | Type             | Null | Key | Default | Extra |
| ------ | --------------------------------------------------------- | ---------------- | ---- | --- | ------- | ----- |
| uri-id | Id of the item-uri table entry that contains the item uri | int unsigned     | NO   | PRI | NULL    |       |
| type   |                                                           | tinyint unsigned | NO   | PRI | 0       |       |
| tid    |                                                           | int unsigned     | NO   | PRI | 0       |       |
| cid    | Contact id of the mentioned public contact                | int unsigned     | NO   | PRI | 0       |       |

## Indexes

| Name    | Fields                 |
| ------- | ---------------------- |
| PRIMARY | uri-id, type, tid, cid |
| tid     | tid                    |
| cid     | cid                    |

## Foreign Keys

| Field  | Target Table                           | Target Field |
| ------ | -------------------------------------- | ------------ |
| uri-id | [item-uri](/spec/database/db_item-uri) | id           |
| tid    | [tag](/spec/database/db_tag)           | id           |
| cid    | [contact](/spec/database/db_contact)   | id           |

Return to [database documentation](/spec/database/)
