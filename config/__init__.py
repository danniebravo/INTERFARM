# Use PyMySQL as the MySQLdb driver so no native MySQL client libs are required.
import pymysql

pymysql.install_as_MySQLdb()
