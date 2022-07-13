---
title: intro
tags:
  - database
  - table
  - developer
---
# Table intro



## Fields

| Field       | Description       | Type               | Null | Key | Default             | Extra          |
| ----------- | ----------------- | ------------------ | ---- | --- | ------------------- | -------------- |
| id          | sequential ID     | int unsigned       | NO   | PRI | NULL                | auto_increment |
| uid         | User id           | mediumint unsigned | NO   |     | 0                   |                |
| fid         | deprecated        | int unsigned       | YES  |     | NULL                |                |
| contact-id  |                   | int unsigned       | NO   |     | 0                   |                |
| suggest-cid | Suggested contact | int unsigned       | YES  |     | NULL                |                |
| knowyou     |                   | boolean            | NO   |     | 0                   |                |
| duplex      | deprecated        | boolean            | NO   |     | 0                   |                |
| note        |                   | text               | YES  |     | NULL                |                |
| hash        |                   | varchar(255)       | NO   |     |                     |                |
| datetime    |                   | datetime           | NO   |     | 0001-01-01 00:00:00 |                |
| blocked     | deprecated        | boolean            | NO   |     | 0                   |                |
| ignore      |                   | boolean            | NO   |     | 0                   |                |

## Indexes

| Name        | Fields      |
| ----------- | ----------- |
| PRIMARY     | id          |
| contact-id  | contact-id  |
| suggest-cid | suggest-cid |
| uid         | uid         |

## Foreign Keys

| Field       | Target Table               | Target Field |
| ----------- | -------------------------- | ------------ |
| uid         | [user](./db_user.md)       | uid          |
| contact-id  | [contact](./db_contact.md) | id           |
| suggest-cid | [contact](./db_contact.md) | id           |

Return to [database documentation](./index.md)
