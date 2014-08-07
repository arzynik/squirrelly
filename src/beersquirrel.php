<?



require_once '/Users/arzynik/Sites/Tipsy/src/Tipsy/Tipsy.php';
//require_once __DIR__ . '/../vendor/autoload.php';

$bs = new Tipsy\Tipsy;

$bs->config('../src/config.ini');

$bs->model('Tipsy\DBO/Upload', [
	byUid => function($id) {
		return $this->q('select * from upload where uid=?', $id)->get(0);
	},
	path => function() {
		return $this->tipsy()->config()['data']['path'].'/'.$this->uid;
	},
	exports => function() {
		$ret = [
			'uid' => $this->uid,
			'date' => $this->date,
			'type' => $this->type,
			'ext' => $this->ext
		];

		if ($this->type == 'text') {
			$ret['content'] = file_get_contents($this->path());
		}
		
		return $ret;
	},
	id => 'id',
	table => 'upload'
]);



$bs->router()
	->when('get/:id', function($Params, $Upload, $Tipsy) {
		$id = explode('.',$Params->id)[0];
		$u = $Upload->byUid($id);

		if (!$u->uid) {
			http_response_code(404);
			exit;
		}
		
		echo $u->json();
	})
	->when('file/:id', function($Params, $Upload, $Tipsy) {
		$id = explode('.',$Params->id)[0];
		$u = $Upload->byUid($id);

		if (!$u->uid) {
			http_response_code(404);
			exit;
		}
		
		$file = $u->path();

		http_response_code(200);
		header('Date: '.date('r'));
		header('Last-Modified: '.date('r',filemtime($file)));
		header('Accept-Ranges: bytes');
		header('Content-Length: '.filesize($file));

		switch ($u->type) {
			case 'text':
				header('Content-type: text/plain');
				break;

			case 'image':
				header('Content-type: image/'.$u->ext);
				break;

			default:
				http_response_code(500);
				exit;
				break;
		}

		readfile($file);
		exit;		
	})
	->post('upload', function($Request, $Upload, $Tipsy) {
		$type = explode('/',$Request->type);
		
		if (preg_match('/^data:[a-z]+\/[a-z]+;base64,(.*)$/',$Request->data)) {
			$data = preg_replace('/^data:[a-z]+\/[a-z]+;base64,(.*)$/','\\1',$Request->data);
			$data = base64_decode($data);
		} else {
			$data = $Request->data;
		}

		if ($type[0] == 'image' && substr($Request->data, 0, 11) == 'data:image/') {
			// image
			switch ($type[1]) {
				case 'png':
				case 'jpg':
				case 'jpeg':
				case 'gif':
					$ext = $type[1];
					$type = 'image';
					break;

				default:
					http_response_code(500);
					exit;
			}

			if ($ext == 'jpeg') {
				$ext = 'jpg';
			}

		} elseif ($type[0] == 'text') {
			// text
			$ext = $type[1];
			$type = 'text';


		} else {
			http_response_code(500);
			exit;
		}

		if (!$data) {
			http_response_code(500);
			exit;
		}

		$u = $Upload->create([
			'date' => date('Y-m-d H:i:s'),
			'type' => $type,
			'ext' => $ext
		])->load();

		file_put_contents($Tipsy->config()['data']['path'].'/'.$u->uid, $data);

		echo $u->json();
	})
	->otherwise(function($View) {
		$View->display('index');
	});

$bs->start();

