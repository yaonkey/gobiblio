package main

import (
	"biblio/addbooks/cmd/api"
	"flag"
	"fmt"
)

var (
	targetUrl  string
	referalKey string // a2775a2b516175006a9f8a5975ab78469d766e40e04a1d4d9e8f3c25efbea774fc8cb7db2f7654734ca0a29e902f5ea12124
	testing    bool
	perPage    int
)

func init() {
	flag.StringVar(&referalKey, "referal-key", "", "Referal key")
	flag.StringVar(&targetUrl, "target-url", "", "Target url for adding books")
	flag.BoolVar(&testing, "testing", false, "Test without adding books")
	flag.IntVar(&perPage, "per-page", 50, "Limit books per page on getting")
}

func main() {
	// run:
	// go run ./cmd/main.go -referal-key=a2775a2b516175006a9f8a5975ab78469d766e40e04a1d4d9e8f3c25efbea774fc8cb7db2f7654734ca0a29e902f5ea12124 -testing >> test.txt
	flag.Parse()
	if testing {
		for i := 0; i < 10; i++ {
			books, _ := api.NewBooks(referalKey, perPage, i)
			fmt.Println(books)
		}
	}
}
