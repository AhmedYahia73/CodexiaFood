<?php

namespace App\trait;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

trait image
{
    // This Trait About Image

    public function upload(Request $request, $fileName, $directory)
    {
        if ($request->has($fileName)) { // if Request has an Image
            $imagePath = $request->file($fileName)->store($directory, 'public');

            return $imagePath;
        }

        return null;
    }

    public function uploadFile_v2($file, $directory)
    {
        if ($file) {
            // توليد اسم جديد للملف بامتداد webp
            $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $newFileName = $name.'_'.time().'.webp';

            // 1. إنشاء كائن الـ Manager بالإصدار الرابع (V4)
            $manager = new ImageManager(new Driver);

            // 2. قراءة الصورة باستخدام كائن الـ manager المعرف
            $image = $manager->decode($file);

            // 3. تحويل الصورة وتحديد الجودة
            $encodedImage = $image->encode(new WebpEncoder(quality: 80));

            // 4. حفظ الصورة
            Storage::disk('public')->put($directory.'/'.$newFileName, $encodedImage);

            return $directory.'/'.$newFileName;
        }

        return null;
    }

    public function upload_image(Request $request, $fileName, $directory)
    {
        if ($request->hasFile($fileName)) {
            $file = $request->file($fileName);

            $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $newFileName = $name.'_'.time().'.webp';

            // الطريقة الصحيحة للإصدار الرابع:
            $manager = new ImageManager(new Driver);
            $image = $manager->decode($file);

            $encodedImage = $image->encode(new WebpEncoder(quality: 80));
            Storage::disk('public')->put($directory.'/'.$newFileName, $encodedImage);

            return $directory.'/'.$newFileName;
        }

        return null;
    }

    public function update_image_v2(Request $request, $old_image_path, $fileName, $directory)
    {
        if ($request->hasFile($fileName)) {
            $newImagePath = $this->upload_image($request, $fileName, $directory);

            if ($newImagePath && $old_image_path && Storage::disk('public')->exists($old_image_path)) {
                Storage::disk('public')->delete($old_image_path);
            }

            return $newImagePath;
        }

        return null;
    }

    public function update_image(Request $request, $old_image_path, $fileName, $directory)
    {
        if ($request->has($fileName)) {
            $imagePath = $request->file($fileName)->store($directory, 'public');
            if ($old_image_path && Storage::disk('public')->exists($old_image_path)) {
                Storage::disk('public')->delete($old_image_path);
            }

            return $imagePath;
        }

        return null;
    }

    public function uploadFile($file, $directory)
    {
        if ($file) {
            return $file->store($directory, 'public');
        }

        return null;
    }

    public function upload_array_of_file(Request $request, $fileName, $directory)
    {
        if ($request->has($fileName)) {
            $uploadedPaths = [];
            foreach ($request->file($fileName) as $file) {
                $imagePath = $file->store($directory, 'public');
                $uploadedPaths[] = $imagePath;
            }

            return $uploadedPaths;
        }

        return null;
    }

    public function deleteImage($imagePath)
    {
        if ($imagePath && ! empty($imagePath) && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }
}
