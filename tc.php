 <?php

header('Content-Type: application/json');

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Methods: GET, POST');

header('Access-Control-Allow-Headers: Content-Type');

class HanedanTCSorgu {

    private $base_url = "https://hanedan.liveblog365.com";

    private $cookie_file = "cookies.txt";

    

    public function __construct() {

        if(file_exists($this->cookie_file)) {

            unlink($this->cookie_file);

        }

    }

    

    private function curl_request($url, $post_data = null, $referer = null) {

        $ch = curl_init();

        

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36');

        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file);

        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);

        curl_setopt($ch, CURLOPT_HEADER, false);

        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        

        if($referer) {

            curl_setopt($ch, CURLOPT_REFERER, $referer);

        }

        

        if($post_data) {

            curl_setopt($ch, CURLOPT_POST, true);

            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));

        }

        

        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        

        return [

            'content' => $response,

            'status' => $http_code

        ];

    }

    

    public function login() {

        // Önce ana sayfaya git

        $home_page = $this->curl_request($this->base_url);

        

        // Login sayfasını al

        $login_page = $this->curl_request($this->base_url . "/login.php");

        

        if($login_page['status'] != 200) {

            error_log("Login page failed: " . $login_page['status']);

            return false;

        }

        

        // Giriş yap

        $login_data = [

            'key' => 'hanedanfree',

            'giris' => ''

        ];

        

        $login_result = $this->curl_request(

            $this->base_url . "/login.php", 

            $login_data, 

            $this->base_url . "/login.php"

        );

        

        // Debug için response'u logla

        error_log("Login response status: " . $login_result['status']);

        error_log("Login response length: " . strlen($login_result['content']));

        

        if($login_result['status'] == 302) {

            // Redirect varsa başarılı

            return true;

        }

        

        // Dashboard kontrolü

        if(strpos($login_result['content'], 'dashboard') !== false || 

           strpos($login_result['content'], 'Hoş geldin') !== false) {

            return true;

        }

        

        // TC sayfasına erişim test et

        $test_page = $this->curl_request($this->base_url . "/tc.php");

        if($test_page['status'] == 200 && strpos($test_page['content'], 'TC Kimlik No Sorgulama') !== false) {

            return true;

        }

        

        return false;

    }

    

    public function tcSorgula($tc_no) {

        // Önce login ol

        $login_result = $this->login();

        if(!$login_result) {

            return [

                'status' => false,

                'message' => 'Giriş başarısız',

                'data' => null

            ];

        }

        

        // TC sorgulama sayfasına git

        $tc_page = $this->curl_request($this->base_url . "/tc.php");

        

        if($tc_page['status'] != 200) {

            return [

                'status' => false,

                'message' => 'TC sayfasına erişilemedi. HTTP: ' . $tc_page['status'],

                'data' => null

            ];

        }

        

        // Debug: Sayfa içeriğini kontrol et

        error_log("TC Page contains 'TC Kimlik': " . (strpos($tc_page['content'], 'TC Kimlik') !== false ? 'YES' : 'NO'));

        

        // TC sorgula

        $sorgu_data = ['tc' => $tc_no];

        $sorgu_result = $this->curl_request(

            $this->base_url . "/tc.php", 

            $sorgu_data, 

            $this->base_url . "/tc.php"

        );

        

        if($sorgu_result['status'] == 200) {

            // Debug: Sorgu sonucunu kontrol et

            error_log("Sorgu response contains 'Bulunan Kişi': " . (strpos($sorgu_result['content'], 'Bulunan Kişi') !== false ? 'YES' : 'NO'));

            error_log("Sorgu response length: " . strlen($sorgu_result['content']));

            

            return $this->bilgileriAyikla($sorgu_result['content'], $tc_no);

        } else {

            return [

                'status' => false,

                'message' => 'Sorgu başarısız. HTTP: ' . $sorgu_result['status'],

                'data' => null

            ];

        }

    }

    

    private function bilgileriAyikla($html, $tc_no) {

        // Debug için HTML'yi kaydet

        file_put_contents('debug_last_response.html', $html);

        

        // Birden fazla tablo pattern'i dene

        $patterns = [

            '/<table[^>]*class="table"[^>]*>(.*?)<\/table>/s',

            '/<table[^>]*>(.*?)<\/table>/s',

            '/<tbody>(.*?)<\/tbody>/s'

        ];

        

        $table_found = false;

        $table_content = '';

        

        foreach($patterns as $pattern) {

            if(preg_match($pattern, $html, $matches)) {

                $table_found = true;

                $table_content = $matches[0];

                error_log("Table found with pattern");

                break;

            }

        }

        

        if(!$table_found) {

            error_log("No table found in HTML");

            return [

                'status' => false,

                'message' => 'Tablo bulunamadı. HTML kaydedildi.',

                'data' => null

            ];

        }

        

        // TR'leri bul

        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $table_content, $rows);

        

        if(!isset($rows[1]) || count($rows[1]) < 2) {

            error_log("Not enough rows found: " . count($rows[1]));

            return [

                'status' => false,

                'message' => 'Yeterli satır bulunamadı',

                'data' => null

            ];

        }

        

        // İkinci satırı al (ilk satır başlık olabilir)

        $data_row = $rows[1][1]; // İkinci satır

        

        // TD'leri bul

        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $data_row, $cells);

        

        if(count($cells[1]) >= 6) {

            $bilgiler = [

                'status' => true,

                'message' => 'Sorgu başarılı',

                'data' => [

                    'tc' => trim(strip_tags($cells[1][0])),

                    'ad_soyad' => trim(strip_tags($cells[1][1])),

                    'dogum_tarihi' => trim(strip_tags($cells[1][2])),

                    'nufus_il_ilce' => trim(strip_tags($cells[1][3])),

                    'anne_bilgisi' => trim(strip_tags($cells[1][4])),

                    'baba_bilgisi' => trim(strip_tags($cells[1][5])),

                    'sorgu_tarihi' => date('Y-m-d H:i:s')

                ],

                'developer' => 'Punishe0',

                'key' => 'hanedanfree'

            ];

            

            error_log("Successfully extracted data for TC: " . $tc_no);

            return $bilgiler;

        } else {

            error_log("Not enough cells: " . count($cells[1]));

            

            // Alternatif parsing dene - direkt regex ile

            return $this->alternatifParsing($html, $tc_no);

        }

    }

    

    private function alternatifParsing($html, $tc_no) {

        // Direkt regex ile veri çekmeyi dene

        $patterns = [

            'tc' => '/<td[^>]*>(' . $tc_no . ')<\/td>/',

            'ad_soyad' => '/<td[^>]*>([A-ZĞÜŞİÖÇ][a-zğüşiöç]+\s+[A-ZĞÜŞİÖÇ][a-zğüşiöç]+)<\/td>/',

            'dogum_tarihi' => '/<td[^>]*>(\d{1,2}\.\d{1,2}\.\d{4})<\/td>/',

            'nufus_il_ilce' => '/<td[^>]*>([A-ZĞÜŞİÖÇ]+\s*\/\s*[A-ZĞÜŞİÖÇ]+)<\/td>/'

        ];

        

        $data = [];

        foreach($patterns as $key => $pattern) {

            if(preg_match($pattern, $html, $matches)) {

                $data[$key] = $matches[1];

            }

        }

        

        if(count($data) >= 3) {

            return [

                'status' => true,

                'message' => 'Sorgu başarılı (alternatif parsing)',

                'data' => array_merge($data, [

                    'sorgu_tarihi' => date('Y-m-d H:i:s')

                ]),

                'developer' => 'Punishe0',

                'key' => 'hanedanfree'

            ];

        }

        

        return [

            'status' => false,

            'message' => 'Bilgiler ayrıştırılamadı',

            'data' => null

        ];

    }

}

// API Başlangıç

$sorgu = new HanedanTCSorgu();

// Hata loglamayı aç

ini_set('display_errors', 0);

error_reporting(E_ALL);

ini_set('error_log', 'api_errors.log');

// GET parametrelerini kontrol et

if(isset($_GET['tc'])) {

    $tc = trim($_GET['tc']);

    

    if(empty($tc) || !is_numeric($tc) || strlen($tc) != 11) {

        echo json_encode([

            'status' => false,

            'message' => 'Geçersiz TC numarası. 11 haneli olmalı.',

            'data' => null

        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        exit;

    }

    

    $result = $sorgu->tcSorgula($tc);

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    

} elseif(isset($_GET['test'])) {

    // Test endpoint

    echo json_encode([

        'status' => true,

        'message' => 'API çalışıyor',

        'developer' => 'Punishe0',

        'key' => 'hanedanfree',

        'timestamp' => date('Y-m-d H:i:s'),

        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'

    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    

} else {

    // API bilgi endpoint

    echo json_encode([

        'status' => true,

        'message' => 'TC Sorgulama API',

        'endpoints' => [

            'GET /api.php?tc=12345678901' => 'TC sorgulama',

            'GET /api.php?test=1' => 'API test',

            'GET /api.php' => 'API bilgi'

        ],

        'parameters' => [

            'tc' => '11 haneli TC kimlik numarası'

        ],

        'developer' => 'Punishe0',

        'key' => 'hanedanfree',

        'version' => '1.1'

    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

}

?>
