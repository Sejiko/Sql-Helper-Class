Here are some examples How to use this Class:

Setup:
(Database) testDB1
- (Table) user
  - name
  - surname
  - nickname
  
- (Table) groups
  - group_name
  - acces_level
  
(Database) testDB2
- (Table) test
  - name
  - nick
-(table) test2
  - age
  - Birthday

Initialize a new SqlObject:
Parameter: SqlBoost(hostname, databasename, username, password, debugging).
By default debugMode is set to Off.
```PHP
<?php
$sql = new SqlBoost(); //Default Values are: 'localhost', 'root'
$sql = new SqlBoost('localhost', '', 'root', ''); 
$sql = new SqlBoost('localhost', '', 'root', '', 1); // Turn on Debug
?>
```

Creating Databases From Setup:
```PHP
<?php

  
 //examples:
 //safe syntax:
 $sql->execute('Select $,$ From $ where column1 = ?', [1], ['column1','column2','table']);
 //unsafe:
 $sql->execute('Select column1,column2 From table where column1 = 1'); if id is from user the sql is vulnerable
 
 //second syntax:
 $sql->select('table', ['column1', 'column2'])->where('column1', '=', 1)->execute();

?>
```
