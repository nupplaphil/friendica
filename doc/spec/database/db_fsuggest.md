---
title: fsuggest
tags:
  - database
  - table
  - developer
---
# Table fsuggest

friend suggestion stuff

## Fields

| Field   | Description | Type               | Null | Key | Default             | Extra          |
| ------- | ----------- | ------------------ | ---- | --- | ------------------- | -------------- |
| id      |             | int unsigned       | NO   | PRI | NULL                | auto_increment |
| uid     | User id     | mediumint unsigned | NO   |     | 0                   |                |
| cid     |             | int unsigned       | NO   |     | 0                   |                |
| name    |             | varchar(255)       | NO   |     |                     |                |
| url     |             | varchar(255)       | NO   |     |                     |                |
| request |             | varchar(255)       | NO   |     |                     |                |
| photo   |             | varchar(255)       | NO   |     |                     |                |
| note    |             | text               | YES  |     | NULL                |                |
| created |             | datetime           | NO   |     | 0001-01-01 00:00:00 |                |

## Indexes

| Name    | Fields   |
| ------- | -------- |
| PRIMARY | id       |
| cid     | cid      |
| uid     | uid      |

## Foreign Keys

| Field | Target Table               | Target Field |
| ----- | -------------------------- | ------------ |
| uid   | [user](./db_user.md)       | uid          |
| cid   | [contact](./db_contact.md) | id           |

Return to [database documentation](./index.md)
