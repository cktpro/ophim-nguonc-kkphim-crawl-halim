<?php
require_once __DIR__ . '/../../../wp-load.php';
require_once __DIR__ . '/../../../wp-admin/includes/taxonomy.php';
require_once __DIR__ . '/../../../wp-admin/includes/image.php';

set_time_limit(0);
define('CRAWL_OPHIM_PATH', plugin_dir_path(__FILE__));
define('CRAWL_OPHIM_PATH_SCHEDULE_JSON', CRAWL_OPHIM_PATH . 'schedule.json');
define('CRAWL_OPHIM_PATH_SOURCE_JSON', CRAWL_OPHIM_PATH . 'schedule_source.json');

require_once CRAWL_OPHIM_PATH . 'constant.php';

if (!isset($argv[1])) return;
if ($argv[1] != get_option(CRAWL_OPHIM_OPTION_SECRET_KEY, 'secret_key')) return;

require_once CRAWL_OPHIM_PATH . 'functions.php';
require_once CRAWL_OPHIM_PATH . 'crawl_function.php';

// Get & Check Settings
$crawl_ophim_settings = json_decode(get_option(CRAWL_OPHIM_OPTION_SETTINGS, false));
if (!$crawl_ophim_settings) return;

// Check enable
if (getEnable() === false) {
	update_option(CRAWL_OPHIM_OPTION_RUNNING, 0);
	return;
}
// Check running
if ((int) get_option(CRAWL_OPHIM_OPTION_RUNNING, 0) === 1) return;

// Update Running
update_option(CRAWL_OPHIM_OPTION_RUNNING, 1);
switch (getEnableSource()) {
	case 1:
		Crawl_Ophim($crawl_ophim_settings);
		break;
	case 2:
		Crawl_KKPhim($crawl_ophim_settings);
		break;
	case 3:
		Crawl_Nguonc($crawl_ophim_settings);
		break;
	default:
		Crawl_Nguonc($crawl_ophim_settings);
		break;
}
function Crawl_Nguonc($crawl_ophim_settings)
{
	try {
		// Crawl Pages
		$pageFrom = $crawl_ophim_settings->pageFrom;
		$pageTo = $crawl_ophim_settings->pageTo;
		$listMovies = array();
		for ($i = $pageFrom; $i >= $pageTo; $i--) {
			if (getEnable() === false) {
				update_option(CRAWL_OPHIM_OPTION_RUNNING, 0);
				return;
			}
			// $result = crawl_ophim_page_handle(API_DOMAIN . "/danh-sach/phim-moi-cap-nhat?page=$i");
			$uri_crawl = $crawl_ophim_settings->url_nguonc_post ? $crawl_ophim_settings->url_nguonc_post : (API_NGUONC . "/api/films/the-loai/hoat-hinh");
			$result = crawl_ophim_page_handle_nguonc($uri_crawl . "/?page=$i");
			$result = explode("\n", $result);
			$listMovies = array_merge($listMovies, $result);
		}
		shuffle($listMovies);
		$countMovies = count($listMovies);
		$countDone = 0;
		$countStatus = array(0, 0, 0, 0, 0);

		write_log("Start Nguonc crawler {$countMovies} movies");
		// Crawl Movies
		foreach ($listMovies as $key => $data_post) {
			if (getEnable() === false) {
				update_option(CRAWL_OPHIM_OPTION_RUNNING, 0);
				write_log("Force Stop => Done {$countDone}/{$countMovies} movies (Nothing Update: {$countStatus[0]} | Insert: {$countStatus[1]} | Update: {$countStatus[2]} | Error: {$countStatus[3]} | Filter: {$countStatus[4]})");
				return;
			}

			$url 								= explode('|', $data_post)[0];
			// $ophim_id 					= explode('|', $data_post)[1];
			$ophim_update_time 	= explode('|', $data_post)[1];
			$result = crawl_ophim_movies_handle_nguonc($url, $ophim_update_time, $crawl_ophim_settings->filterType, $crawl_ophim_settings->filterCategory, $crawl_ophim_settings->filterCountry);
			$result = json_decode($result);
			if ($result->schedule_code == SCHEDULE_CRAWLER_TYPE_ERROR) write_log(sprintf("ERROR: %s ==>>> %s", $url, $result->msg));
			$countStatus[$result->schedule_code]++;
			$countDone++;
		}
	} catch (\Throwable $th) {
		write_log(sprintf("ERROR: THROW ==>>> %s", $th->getMessage()));
	}
	// Update Running

	update_option(CRAWL_OPHIM_OPTION_RUNNING, 0);

	write_log("Done {$countDone}/{$countMovies} movies (Nothing Update: {$countStatus[0]} | Insert: {$countStatus[1]} | Update: {$countStatus[2]} | Error: {$countStatus[3]} | Filter: {$countStatus[4]})");
	$notify ="Done {$countDone}/{$countMovies} movies nguonc: \n Nothing Update: {$countStatus[0]} \n Insert: {$countStatus[1]} \n Update: {$countStatus[2]} \n Error: {$countStatus[3]} \n Filter: {$countStatus[4]}";
	sendNotifiTelegram($notify);
}
function Crawl_KKPhim($crawl_ophim_settings)
{
	try {
		// Crawl Pages
		$pageFrom = $crawl_ophim_settings->pageFrom;
		$pageTo = $crawl_ophim_settings->pageTo;
		$listMovies = array();
		for ($i = $pageFrom; $i >= $pageTo; $i--) {
			if (getEnable() === false) {
				update_option(CRAWL_OPHIM_OPTION_RUNNING, 0);
				return;
			}
			// $result = crawl_ophim_page_handle(API_DOMAIN . "/danh-sach/phim-moi-cap-nhat?page=$i");
			$result = crawl_ophim_page_handle_kkphim(API_KKPHIM . "/danh-sach/phim-moi-cap-nhat?page=$i");

			$result = explode("\n", $result);
			$listMovies = array_merge($listMovies, $result);
		}
		shuffle($listMovies);

		$countMovies = count($listMovies);
		$countDone = 0;
		$countStatus = array(0, 0, 0, 0, 0);

		write_log("Start KKphim crawler {$countMovies} movies");
		// Crawl Movies
		foreach ($listMovies as $key => $data_post) {
			if (getEnable() === false) {
				update_option(CRAWL_OPHIM_OPTION_RUNNING, 0);
				write_log("Force Stop => Done {$countDone}/{$countMovies} movies (Nothing Update: {$countStatus[0]} | Insert: {$countStatus[1]} | Update: {$countStatus[2]} | Error: {$countStatus[3]} | Filter: {$countStatus[4]})");
				return;
			}

			$url 								= explode('|', $data_post)[0];
			$kkphim_id 					= explode('|', $data_post)[1];
			$kkphim_update_time 	= explode('|', $data_post)[2];

			$result = crawl_kkphim_movies_handle($url, $kkphim_id, $kkphim_update_time, $crawl_ophim_settings->filterType, $crawl_ophim_settings->filterCategory, $crawl_ophim_settings->filterCountry);
			$result = json_decode($result);
			if ($result->schedule_code == SCHEDULE_CRAWLER_TYPE_ERROR) write_log(sprintf("ERROR: %s ==>>> %s", $url, $result->msg));
			$countStatus[$result->schedule_code]++;
			$countDone++;
		}
	} catch (\Throwable $th) {
		write_log(sprintf("ERROR: THROW ==>>> %s", $th->getMessage()));
	}
	// Update Running

	update_option(CRAWL_OPHIM_OPTION_RUNNING, 0);

	write_log("Done {$countDone}/{$countMovies} movies (Nothing Update: {$countStatus[0]} | Insert: {$countStatus[1]} | Update: {$countStatus[2]} | Error: {$countStatus[3]} | Filter: {$countStatus[4]})");
	$notify ="Done {$countDone}/{$countMovies} movies kkphim: \n Nothing Update: {$countStatus[0]} \n Insert: {$countStatus[1]} \n Update: {$countStatus[2]} \n Error: {$countStatus[3]} \n Filter: {$countStatus[4]}";
	sendNotifiTelegram($notify);
}
function Crawl_OPhim($crawl_ophim_settings)
{
	try {
		// Crawl Pages
		$pageFrom = $crawl_ophim_settings->pageFrom;
		$pageTo = $crawl_ophim_settings->pageTo;
		$listMovies = array();
		for ($i = $pageFrom; $i >= $pageTo; $i--) {
			if (getEnable() === false) {
				update_option(CRAWL_OPHIM_OPTION_RUNNING, 0);
				return;
			}
			// $result = crawl_ophim_page_handle(API_DOMAIN . "/danh-sach/phim-moi-cap-nhat?page=$i");
			$result = crawl_ophim_page_handle(API_DOMAIN . "/danh-sach/phim-moi-cap-nhat?page=$i");

			$result = explode("\n", $result);
			$listMovies = array_merge($listMovies, $result);
		}
		shuffle($listMovies);

		$countMovies = count($listMovies);
		$countDone = 0;
		$countStatus = array(0, 0, 0, 0, 0);

		write_log("Start Ophim crawler {$countMovies} movies");
		// Crawl Movies
		foreach ($listMovies as $key => $data_post) {
			if (getEnable() === false) {
				update_option(CRAWL_OPHIM_OPTION_RUNNING, 0);
				write_log("Force Stop => Done {$countDone}/{$countMovies} movies (Nothing Update: {$countStatus[0]} | Insert: {$countStatus[1]} | Update: {$countStatus[2]} | Error: {$countStatus[3]} | Filter: {$countStatus[4]})");
				return;
			}

			$url 								= explode('|', $data_post)[0];
			$ophim_id 					= explode('|', $data_post)[1];
			$ophim_update_time 	= explode('|', $data_post)[2];

			$result = crawl_ophim_movies_handle($url, $ophim_id, $ophim_update_time, $crawl_ophim_settings->filterType, $crawl_ophim_settings->filterCategory, $crawl_ophim_settings->filterCountry);
			$result = json_decode($result);
			if ($result->schedule_code == SCHEDULE_CRAWLER_TYPE_ERROR) write_log(sprintf("ERROR: %s ==>>> %s", $url, $result->msg));
			$countStatus[$result->schedule_code]++;
			$countDone++;
		}
	} catch (\Throwable $th) {
		write_log(sprintf("ERROR: THROW ==>>> %s", $th->getMessage()));
	}
	// Update Running

	update_option(CRAWL_OPHIM_OPTION_RUNNING, 0);

	write_log("Done {$countDone}/{$countMovies} movies (Nothing Update: {$countStatus[0]} | Insert: {$countStatus[1]} | Update: {$countStatus[2]} | Error: {$countStatus[3]} | Filter: {$countStatus[4]})");
	$notify ="Done {$countDone}/{$countMovies} movies ophim: \n Nothing Update: {$countStatus[0]} \n Insert: {$countStatus[1]} \n Update: {$countStatus[2]} \n Error: {$countStatus[3]} \n Filter: {$countStatus[4]}";
	sendNotifiTelegram($notify);
	
}


// // Update Running

// update_option(CRAWL_OPHIM_OPTION_RUNNING, 0);

// write_log("Done {$countDone}/{$countMovies} movies (Nothing Update: {$countStatus[0]} | Insert: {$countStatus[1]} | Update: {$countStatus[2]} | Error: {$countStatus[3]} | Filter: {$countStatus[4]})");

function getEnable()
{
	$schedule = json_decode(file_get_contents(CRAWL_OPHIM_PATH_SCHEDULE_JSON));
	if ($schedule->enable) {
		return $schedule->enable;
	}
	return false;
}
function getEnableSource()
{
	$source_phim = json_decode(file_get_contents(CRAWL_OPHIM_PATH_SCHEDULE_JSON));
	if ($source_phim->enable_ophim) {
		return 1;
	} elseif ($source_phim->enable_kkphim) {
		return 2;
	} elseif ($source_phim->enable_nguonc) {
		return 3;
	} else return false;
}

function write_log($log_msg, $new_line = "\n")
{
	$log_filename = __DIR__ . '/../../crawl_ophim_logs';
	if (!file_exists($log_filename)) {
		mkdir($log_filename, 0777, true);
	}
	$log_file_data = $log_filename . '/log_' . date('d-m-Y') . '.log';
	file_put_contents($log_file_data, '[' . date("d-m-Y H:i:s") . '] ' . $log_msg . $new_line, FILE_APPEND);
}
function sendNotifiTelegram($notify)
{
	$botToken = "7872689878:AAEKkbhnVAe7DR2-9vg5JgGknhH9ShXsWfQ"; // Token của bot
	$chatId = "1997128476";     // ID chat
	$message = "HH3DVIP Cập Nhật Phim Thành Công \n $notify"; // Nội dung tin nhắn

	// URL gửi yêu cầu đến Telegram API
	$url = "https://api.telegram.org/bot$botToken/sendMessage";

	// Dữ liệu cần gửi
	$data = [
		'chat_id' => $chatId,
		'text' => $message,
	];

	 // Chuyển dữ liệu thành JSON
	 $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
        ],
    ];
	$context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

	 // Kiểm tra kết quả
	//  if ($response === FALSE) {
    //     echo "Gửi tin nhắn thất bại.";
    // } else {
    //     $responseData = json_decode($response, true);
    //     if ($responseData['ok']) {
    //         echo "Tin nhắn đã được gửi!";
    //     } else {
    //         echo "Lỗi: " . $responseData['description'];
    //     }
    // }
}
