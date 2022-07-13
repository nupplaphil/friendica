---
title: notify-threads
tags:
  - database
  - table
  - developer
---
# Table notify-threads



## Fields

| Field                | Description                                   | Type               | Null | Key | Default | Extra          |
| -------------------- | --------------------------------------------- | ------------------ | ---- | --- | ------- | -------------- |
| id                   | sequential ID                                 | int unsigned       | NO   | PRI | NULL    | auto_increment |
| notify-id            |                                               | int unsigned       | NO   |     | 0       |                |
| master-parent-item   | Deprecated                                    | int unsigned       | YES  |     | NULL    |                |
| master-parent-uri-id | Item-uri id of the parent of the related post | int unsigned       | YES  |     | NULL    |                |
| parent-item          |                                               | int unsigned       | NO   |     | 0       |                |
| receiver-uid         | User id                                       | mediumint unsigned | NO   |     | 0       |                |

## Indexes

| Name                 | Fields               |
| -------------------- | -------------------- |
| PRIMARY              | id                   |
| master-parent-uri-id | master-parent-uri-id |
| receiver-uid         | receiver-uid         |
| notify-id            | notify-id            |

## Foreign Keys

| Field                | Target Table                 | Target Field |
| -------------------- | ---------------------------- | ------------ |
| notify-id            | [notify](./db_notify.md)     | id           |
| master-parent-uri-id | [item-uri](./db_item-uri.md) | id           |
| receiver-uid         | [user](./db_user.md)         | uid          |

Return to [database documentation](./index.md)
