<?php
// app/model/Voice.php
namespace app\model;

use think\Model;

class Voice extends Model
{
    protected $name = 'voice';
    protected $autoWriteTimestamp = false;

    /**
     * 获取可用的音色列表
     * @param string $lang 语言，可选
     * @return array
     */
    public function getAvailableVoices($lang = null)
    {
        $query = $this->where('status', 1);

        if (!empty($lang)) {
            $query->where('lang', $lang);
        }

        return $query->order('weigh ASC')
            ->field('id, name, lang, weigh')
            ->select()
            ->toArray();
    }

    /**
     * 检查音色是否存在且可用
     * @param string $voiceId 音色ID
     * @return bool
     */
    public function isVoiceAvailable($voiceId)
    {
        return $this->where(['voice_id' => $voiceId, 'status' => 1])->find() !== null;
    }

    /**
     * 获取音色信息
     * @param string $voiceId 音色ID
     * @return array|null
     */
    public function getVoiceInfo($voiceId)
    {
        return $this->where(['voice_id' => $voiceId, 'status' => 1])->find()?->toArray();
    }
}
