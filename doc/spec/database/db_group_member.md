---
title: group_member
tags:
  - database
  - table
  - developer
---
# Table group_member

privacy groups, member info

## Fields

| Field      | Description                                               | Type         | Null | Key | Default | Extra          |
| ---------- | --------------------------------------------------------- | ------------ | ---- | --- | ------- | -------------- |
| id         | sequential ID                                             | int unsigned | NO   | PRI | NULL    | auto_increment |
| gid        | groups.id of the associated group                         | int unsigned | NO   |     | 0       |                |
| contact-id | contact.id of the member assigned to the associated group | int unsigned | NO   |     | 0       |                |

## Indexes

| Name          | Fields                  |
| ------------- | ----------------------- |
| PRIMARY       | id                      |
| contactid     | contact-id              |
| gid_contactid | UNIQUE, gid, contact-id |

## Foreign Keys

| Field      | Target Table                         | Target Field |
| ---------- | ------------------------------------ | ------------ |
| gid        | [group](/spec/database/db_group)     | id           |
| contact-id | [contact](/spec/database/db_contact) | id           |

Return to [database documentation](/spec/database/)
