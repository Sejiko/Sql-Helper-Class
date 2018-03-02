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

//Initialize a new SqlObject
```PHP
<?php

//arguments are like: SqlBoost(hostname, databasename, username, password, debugging)
$sql = new SqlBoost('localhost', '', 'root', '')    //By default Debugging is set to OFF
$sql = new SqlBoost('localhost', '', 'root', '', 1) // Turn on Debug with the Last argument: 1

?>
```

Creating Databases From Setup:

<?php
  $sql->createDatabase('testDB1');
  
  //Because creating a Table is to complex and doesnt makes it esier we have to use our Core function like so:
  //The Core function gets later in more Detail explained.
  
  $query = "Create Table % ();"
  $table(s) = '' or [];
  $columns = [];
  $values = [];
    
  $sql->execute($query, $table, $columns, $values);
  
  

?>
