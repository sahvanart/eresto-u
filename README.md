# eresto-u

My first PHP project. The idea was to create an application that enables students to order their meal at a fictive canteen remotely so that the could still benefit of it during covid times.

This is a localhost version. You should have php installed, a web server and a mysql server running. Also, you should import the database, which can be done by importing the `.sql` file provided in the `bd` directory.

## database relational schema

`eRestoU_bd` database contains 5 tables, and allows the restaurant to save students infos, record what meals they took and/or comment, and the different menus with the dishes that compose them.

![eresto_u database schema](/images/eRestoU_bdd.png)

## tables

| Field           |   Type    |                              Description |
| --------------- | :-------: | ---------------------------------------: |
| etNumero        |  int(11)  |          primary key, student identifier |
| etNom           | char(50)  |                      student's last name |
| etPrenom        | char(80)  |                     student's first name |
| etLogin         |  char(8)  |                candidate key, user login |
| etMotDePasse    | char(255) |           user password, stored ciphered |
| etDateNaissance |  int(8)   | student date of birth (format: AAAAMMJJ) |
