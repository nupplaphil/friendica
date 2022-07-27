---
title: inbox-entry-receiver
tags:
  - database
  - table
  - developer
---
# Table inbox-entry-receiver

Receiver for the incoming activity

## Fields

| Field    | Description | Type               | Null | Key | Default | Extra |
| -------- | ----------- | ------------------ | ---- | --- | ------- | ----- |
| queue-id |             | int unsigned       | NO   | PRI | NULL    |       |
| uid      | User id     | mediumint unsigned | NO   | PRI | NULL    |       |

## Indexes

| Name    | Fields        |
| ------- | ------------- |
| PRIMARY | queue-id, uid |
| uid     | uid           |

## Foreign Keys

| Field    | Target Table                       | Target Field |
| -------- | ---------------------------------- | ------------ |
| queue-id | [inbox-entry](./db_inbox-entry.md) | id           |
| uid      | [user](./db_user.md)               | uid          |

Return to [database documentation](./index.md)
