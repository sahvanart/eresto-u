# eresto-u

My first PHP project. The idea was to create an application that enables students to order their meal at a fictive canteen remotely so that the could still benefit of it during covid times.

This is a localhost version. You should have php installed, a web server and a mysql server running. Also, you should import the database, which can be done by importing the `.sql` file provided in the `bd` directory.

## database relational schema

`eRestoU_bd` database contains 5 tables, and allows the restaurant to save students infos, record what meals they took and/or comment, and the different menus with the dishes that compose them.

![eresto_u database schema](/images/eRestoU_bdd.png)

## database tables

`etudiant` table contains all students registered to the service.

| Field           |   Type    |                              Description |
| --------------- | :-------: | ---------------------------------------: |
| etNumero        |  int(11)  |          primary key, student identifier |
| etNom           | char(50)  |                      student's last name |
| etPrenom        | char(80)  |                     student's first name |
| etLogin         |  char(8)  |                candidate key, user login |
| etMotDePasse    | char(255) |           user password, stored ciphered |
| etDateNaissance |  int(8)   | student date of birth (format: AAAAMMJJ) |

`repas` table contains all meals ordered by students each day.

| Field      |  Type   |                               Description |
| ---------- | :-----: | ----------------------------------------: |
| reDate     | int(8)  | primary key, meal date (format: AAAAMMJJ) |
| rePlat     | int(11) |      primary key, dish ordered for a meal |
| reEtudiant | int(11) |   primary key, student that took the meal |
| reQuantite | int(1)  |  dish quantity in the meal (0 not stored) |

`commentaire` table contains all students' comments associated to a meal.

| Field             |    Type    |                             Description |
| ----------------- | :--------: | --------------------------------------: |
| coDateRepas       |   int(8)   |               primary key, comment date |
| coEtudiant        |  int(11)   |                  primary key, author id |
| coTexte           |    text    |                            comment text |
| coNote            |   int(1)   |              comment rate (from 0 to 5) |
| coDatePublication | bigint(12) | comment publication or last update date |

`plat` table contains all dishes that compose the menus.

| Field       |   Type    |                           Description |
| ----------- | :-------: | ------------------------------------: |
| plID        |  int(11)  | primary key, auto id, dish identifier |
| plNom       | char(100) |                             dish name |
| plCategorie |   enum    |                         dish category |

`menu` table stores menus with the dates they are proposed.

| Field  |  Type   |                               Description |
| ------ | :-----: | ----------------------------------------: |
| meDate | int(8)  | primary key, menu date (format: AAAAMMJJ) |
| mePlat | int(11) |      primary key, dish composing the menu |
