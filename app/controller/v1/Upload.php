<?php

namespace app\controller\v1;

use app\BaseController;

class Upload extends BaseController
{
    
    public function upload(){
        // 获取单个上传文件
        $file = $this->request->file('file');
        
        // 检查文件是否存在
        if (empty($file)) {
            return json(['code' => 0, 'msg' => '请选择要上传的文件', 'data' => null]);
        }
        
        try {
            // 验证文件：大小不超过10MB，格式为jpg/png/gif，尺寸限制
            $validate = validate([
                'file' => 'fileSize:10485760|fileExt:jpg,png,gif'
            ]);
            
            if (!$validate->check(['file' => $file])) {
                return json(['code' => 0, 'msg' => $validate->getError(), 'data' => null]);
            }
            
            // 获取文件扩展名
            $ext = strtolower($file->getOriginalExtension());
            
            // 构建存储路径：topic/当前日期/文件格式
            $datePath = date('Ymd');
            $customPath = "upload/{$datePath}/{$ext}";
            
            // 生成唯一文件名
            $fileName = md5_file($file->getPathname()) . '.' . $ext;
            
            // 保存文件到自定义路径
            $savedPath = \think\facade\Filesystem::disk('public')->putFileAs($customPath, $file, $fileName);
            
            // 返回成功响应，添加完整的访问URL
            $fullUrl = '/storage/' . $savedPath;
            return json(['code' => 1, 'msg' => '文件上传成功', 'data' => ['url' => $fullUrl, 'path' => $savedPath]]);
            
        } catch (\think\exception\ValidateException $e) {
            // 验证异常
            return json(['code' => 0, 'msg' => $e->getMessage(), 'data' => null]);
        } catch (\Exception $e) {
            // 其他异常
            return json(['code' => 0, 'msg' => '文件上传失败：' . $e->getMessage(), 'data' => null]);
        }
    }

}