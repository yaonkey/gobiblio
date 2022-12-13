package api

import (
	"database/sql"
	"fmt"
)

type Database struct {
	Driver   string
	Host     string
	Port     string
	User     string
	Password string
	Dbname   string
}

func (db *Database) NewConnect() (*sql.DB, error) {
	conn := fmt.Sprintf("host=%s port=%s user=%s password=%s dbname=%s sslmode=disable", db.Host, db.Port, db.User, db.Password, db.Dbname)
	d, err := sql.Open(db.Driver, conn)
	if err != nil {
		return nil, err
	}
	defer d.Close()
	return d, nil
}

func (db *Database) AddBook(table string, book Data) error {
	conn, err := db.NewConnect()
	if err != nil {
		return err
	}
	defer conn.Close()

	sql := fmt.Sprintf("INSERT INTO %s () VALUES ()", table)
	conn.Exec(sql)

	return nil
}
