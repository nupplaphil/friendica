---
title: profile_field
tags:
  - database
  - table
  - developer
---
# Table profile_field

Custom profile fields

## Fields

| Field   | Description                                                 | Type               | Null | Key | Default             | Extra          |
| ------- | ----------------------------------------------------------- | ------------------ | ---- | --- | ------------------- | -------------- |
| id      | sequential ID                                               | int unsigned       | NO   | PRI | NULL                | auto_increment |
| uid     | Owner user id                                               | mediumint unsigned | NO   |     | 0                   |                |
| order   | Field ordering per user                                     | mediumint unsigned | NO   |     | 1                   |                |
| psid    | ID of the permission set of this profile field - 0 = public | int unsigned       | YES  |     | NULL                |                |
| label   | Label of the field                                          | varchar(255)       | NO   |     |                     |                |
| value   | Value of the field                                          | text               | YES  |     | NULL                |                |
| created | creation time                                               | datetime           | NO   |     | 0001-01-01 00:00:00 |                |
| edited  | last edit time                                              | datetime           | NO   |     | 0001-01-01 00:00:00 |                |

## Indexes

| Name    | Fields   |
| ------- | -------- |
| PRIMARY | id       |
| uid     | uid      |
| order   | order    |
| psid    | psid     |

## Foreign Keys

| Field | Target Table                           | Target Field |
| ----- | -------------------------------------- | ------------ |
| uid   | [user](./db_user.md)                   | uid          |
| psid  | [permissionset](./db_permissionset.md) | id           |

Return to [database documentation](./index.md)
