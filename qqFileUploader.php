<?php
/**
 * Handle file uploads via XMLHttpRequest
 */
class qqUploadedFileXhr
{
    /**
     * Save the file to the specified path
     * @param $path
     * @return boolean TRUE on success
     */
    function save($path)
    {
        $input = fopen("php://input", "r");
        $temp = tmpfile();
        $realSize = stream_copy_to_stream($input, $temp);
        fclose($input);

        if ($realSize != $this->getSize()) {
            return false;
        }

        $target = fopen($path, "w");
        fseek($temp, 0, SEEK_SET);
        stream_copy_to_stream($temp, $target);
        fclose($target);

        return true;
    }

    function getName()
    {
        return $_GET['qqfile'];
    }

    function getSize()
    {
        if (isset($_SERVER["CONTENT_LENGTH"])) {
            return (int)$_SERVER["CONTENT_LENGTH"];
        } else {
            throw new Exception('Getting content length is not supported.');
        }
    }

    /**
     * Получение Content-type
     * @return mixed
     */
    function getContentType()
    {
        return 'Случайная строка';
    }
}

/**
 * Handle file uploads via regular form post (uses the $_FILES array)
 */
class qqUploadedFileForm
{
    /**
     * Save the file to the specified path
     * @param $path
     * @return boolean TRUE on success
     */
    function save($path)
    {
        assert(move_uploaded_file($_FILES['qqfile']['tmp_name'], $path));
    }

    function getName()
    {
        return $_FILES['qqfile']['name'];
    }

    function getSize()
    {
        return $_FILES['qqfile']['size'];
    }

    /**
     * Получение Content-type
     * @return mixed
     */
    function getContentType()
    {
        return $_FILES['qqfile']["type"];
    }
}

class qqFileUploader
{
    private $allowedExtensions = array();
    private $sizeLimit = 100485760;
    public $file;

    function __construct(array $allowedExtensions = array(), $sizeLimit = 10485760)
    {
        $allowedExtensions = array_map("strtolower", $allowedExtensions);

        $this->allowedExtensions = $allowedExtensions;
        $this->sizeLimit = $sizeLimit;

        $this->checkServerSettings();

        if (isset($_GET['qqfile'])) {
            $this->file = new qqUploadedFileXhr();
        } elseif (isset($_FILES['qqfile'])) {
            $this->file = new qqUploadedFileForm();
        } else {
            $this->file = false;
        }
    }

    private function checkServerSettings()
    {
        $postSize = $this->toBytes(ini_get('post_max_size'));
        $uploadSize = $this->toBytes(ini_get('upload_max_filesize'));

        if ($postSize < $this->sizeLimit || $uploadSize < $this->sizeLimit) {
            $size = max(1, $this->sizeLimit / 1024 / 1024) . 'M';
            die("{'error':'increase post_max_size and upload_max_filesize to $size'}");
        }
    }

    private function toBytes($str)
    {
        $val = trim($str);
        $last = strtolower($str[strlen($str) - 1]);
        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }

    /**
     * Returns array('success'=>true) or array('error'=>'error message')
     * @param $uploadDirectory
     * @param $newFileId
     * @internal param string $newFileName Имя файла
     * @return array
     */
    function handleUpload($uploadDirectory, $newFileId)
    {
        $newFileId = intval($newFileId);
        if (!is_int($newFileId))
            throw new Exception('Здесь обязательно должен быть int - id картинки');

        if (!is_writable($uploadDirectory)) {
            throw new Exception("Server error. Upload directory \"$uploadDirectory\" isn't writable.");
        }

        if (!$this->file) {
            throw new Exception('No files were uploaded.');
        }

        $size = $this->file->getSize();

        if ($size == 0) {
            return array('error' => 'File is empty');
        }

        if ($size > $this->sizeLimit) {
            return array('error' => 'File is too large');
        }

        $pathinfo = pathinfo($this->file->getName());
        $ext = strtolower($pathinfo['extension']);

        if ($this->allowedExtensions && !in_array(strtolower($ext), $this->allowedExtensions)) {
            $these = implode(', ', $this->allowedExtensions);
            return array('error' => 'File has an invalid extension, it should be one of ' . $these . '.');
        }

        // Генерируем новое имя файла на основании ID
        $newFileName = $uploadDirectory . $newFileId . '.' . $ext;
        assert(!empty($newFileName));

        if ($this->file->save($newFileName)) {
            $fn = $this->file->getName();
            assert(!empty($fn));
            return
                array(
                    'success' => true,
                    'name' => $this->file->getName(),
                    'filename' => $newFileName,
                    'content_type' => $this->file->getContentType(),
                );
        } else {
            throw new Exception('Could not save uploaded file. The upload was cancelled, or server error encountered');
        }

    }
}
