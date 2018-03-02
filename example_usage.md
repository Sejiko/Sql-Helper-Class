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
  $sql->createDatabase('testDB1');
  
  //Because creating a Table is to complex and doesnt makes it esier we have to use our Core function like so:
  //The Core function gets later in more Detail explained.
  
  $query = "CREATE TABLE % (
    % INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    % varchar(8),
    % varchar(8),
    % varchar(16)
  );"
  
  $table = 'user';
  $columns = ['id', 'name', 'surname', 'nickname'];
    
  $sql->execute($query, $table, $columns, $values);

?>
```
