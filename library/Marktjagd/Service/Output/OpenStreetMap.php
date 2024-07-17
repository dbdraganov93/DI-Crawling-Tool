<?php

class Marktjagd_Service_Output_OpenStreetMap
{
    private const SEARCH_API_URL = 'https://nominatim.openstreetmap.org/search.php?format=jsonv2';
    private const REVERSE_API_URL = 'https://nominatim.openstreetmap.org/reverse.php?format=jsonv2';
    private const DEFAULT_ZOOM = 18;
    private Marktjagd_Service_Text_Url $urlService;

    public function __construct()
    {
        $this->urlService = new Marktjagd_Service_Text_Url();
    }

    public function findAddress(string $address, string $countryCode): array
    {
        $parameters = [
            'q' => urlencode($address),
            'countrycodes' => $countryCode,
            'polygon_geojson' => 1,
        ];
        $url = $this->urlService->addParameters(self::SEARCH_API_URL , $parameters);
        $data = $this->getResponse($url);

        return $data[0]?: [];
    }

    public function findAddressFromCoordinates(string $latitude, string $longitude): array
    {
        $parameters = [
            'lat' => urlencode($latitude),
            'lon' => urlencode($longitude),
            'zoom' => self::DEFAULT_ZOOM,
        ];

        $url = $this->urlService->addParameters(self::REVERSE_API_URL , $parameters);
        $data = $this->getResponse($url);

        return $data['address'] ?? [];

    }

    private function getResponse(string $url): array
    {
        $options = [
            'http' => [
                'header' => "User-Agent: Biedronka/1.0\r\n",
            ]
        ];

        return json_decode(file_get_contents($url, false, stream_context_create($options)), true);
    }
}
