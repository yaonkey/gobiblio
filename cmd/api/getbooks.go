package api

import (
	"bytes"
	"encoding/json"
	"errors"
	"io/ioutil"
	"net/http"
	"strconv"
)

type Books struct {
	Page        int    `json:"per_page"`
	CurrentPage int    `json:"current_page"`
	LastPage    int    `json:"last_page"`
	Count       int    `json:"count"`
	Total       int    `json:"total"`
	Data        []Data `json:"data"`
}

type Data struct {
	Id                int       `json:"id"`
	Title             string    `json:"title"`
	Bio               string    `json:"bio"`
	Cover             string    `json:"cover"`
	Sizes             DataSizes `json:"sizes"`
	Duration          int       `json:"duration"`
	Rating            int       `json:"rating"`
	CommentsCount     int       `json:"comments_count"`
	ForSubscribers    bool      `json:"for_subscribers"`
	Amount            int       `json:"amount"`
	Plus18            bool      `json:"plus_18"`
	Plus16            bool      `json:"plus_16"`
	WithMusic         bool      `json:"with_music"`
	NotFinished       bool      `json:"not_finished"`
	CreatedAt         string    `json:"created_at"`
	UpdatedAt         string    `json:"updated_at"`
	TracksCount       int       `json:"tracks_count"`
	Author            string    `json:"author_name"`
	Reader            string    `json:"reader_name"`
	Series            string    `json:"series_name"`
	SeriesNum         int       `json:"series_num"`
	Genres            string    `json:"genres"`
	GenresId          string    `json:"genres_id"`
	Lang              string    `json:"lang_name"`
	Meta              Metadata  `json:"meta_data"`
	Audio             string    `json:"audio_sample"`
	OriginalSizeCover string    `json:"original_size_cover"`
	SaleClosed        bool      `json:"sale_closed"`
	PublishDate       string    `json:"publish_date"`
}

type DataSizes struct {
	Low    int `json:"low"`
	Medium int `json:"medium"`
	High   int `json:"high"`
}

type Metadata struct {
	Translater string `json:"translate_author"`
	Copyright  string `json:"copyright_holder"`
	Note       string `json:"biblio_note"`
	Publisher  string `json:"publisher"`
}

type apiRequest struct {
	referalKey string `json:"referal_key"`
	perPage    int    `json:"per_page"`
	page       int    `json:"page"`
}

func NewBooks(referalKey string, booksCount, page int) (*Books, error) {
	request := &apiRequest{
		referalKey: referalKey,
		perPage:    booksCount,
		page:       page,
	}

	payloadBytes, err := json.Marshal(request)
	if err != nil {
		return nil, err
	}
	body := bytes.NewReader(payloadBytes)

	req, err := http.NewRequest("GET", GetFullApiUrl("api/ref/data/catalog/full")+"?per_page="+strconv.FormatInt(int64(request.perPage), 10)+"&page="+strconv.FormatInt(int64(request.page), 10), body)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Biblio-Auth", "Bearer "+request.referalKey)

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}

	if resp.StatusCode != 200 {
		return nil, errors.New("status code not 200")
	}

	dat, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		return nil, err
	}

	books := &Books{}
	if uerr := json.Unmarshal(dat, &books); uerr != nil {
		return nil, uerr
	}
	defer resp.Body.Close()

	return books, nil
}
