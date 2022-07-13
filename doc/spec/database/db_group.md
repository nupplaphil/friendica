---
title: group
tags:
  - database
  - table
  - developer
---
# Table group

privacy groups, group info

## Fields

| Field   | Description                                                                               | Type               | Null | Key | Default | Extra          |
| ------- | ----------------------------------------------------------------------------------------- | ------------------ | ---- | --- | ------- | -------------- |
| id      | sequential ID                                                                             | int unsigned       | NO   | PRI | NULL    | auto_increment |
| uid     | Owner User id                                                                             | mediumint unsigned | NO   |     | 0       |                |
| visible | 1 indicates the member list is not private                                                | boolean            | NO   |     | 0       |                |
| deleted | 1 indicates the group has been deleted                                                    | boolean            | NO   |     | 0       |                |
| cid     | Contact id of forum. When this field is filled then the members are synced automatically. | int unsigned       | YES  |     | NULL    |                |
| name    | human readable name of group                                                              | varchar(255)       | NO   |     |         |                |

## Indexes

| Name    | Fields   |
| ------- | -------- |
| PRIMARY | id       |
| uid     | uid      |
| cid     | cid      |

## Foreign Keys

| Field | Target Table               | Target Field |
| ----- | -------------------------- | ------------ |
| uid   | [user](./db_user.md)       | uid          |
| cid   | [contact](./db_contact.md) | id           |

Return to [database documentation](./index.md)
