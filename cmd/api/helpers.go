package api

const ApiUrl = "https://admin.bibliovk.ru/"

func GetFullApiUrl(api string) string {
	return ApiUrl + api
}
