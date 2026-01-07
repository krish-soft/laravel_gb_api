<?php

namespace App\Traits;

trait ApiResponserTrait
{


    protected static function showSuccessMessage($message, $statusCode = 200): \Illuminate\Http\JsonResponse
    {
        return self::successResponse($message, [], $statusCode);
    }

    protected static function showErrorMessage($message, $statusCode = 200): \Illuminate\Http\JsonResponse
    {
        return self::errorResponse($message, $statusCode);
    }


    protected static function successResponse($message, $data, $statusCode): \Illuminate\Http\JsonResponse
    {
        return response()->json(
            ['isSuccess' => true, 'message' => $message, 'statusCode' => $statusCode, 'data' => $data],
            $statusCode,
            ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'],
            JSON_UNESCAPED_UNICODE
        );
    }

    protected static function errorResponse($message, $statusCode): \Illuminate\Http\JsonResponse
    {
        if (is_array($message)) {
            // If $message is an array, convert it to a string representation
            if (self::is_multi_dimensional($message)) {
                $singleMessage = self::convertArrayToSingle($message);
            } else {
                $singleMessage = $message;
            }
            // $message = json_encode($singleMessage);
            $message = implode(';', $singleMessage ?? $message);
        }

        return response()->json([
            'isSuccess' => false,
            'message' => $message,
            'statusCode' => $statusCode,
            'data' => []
        ], 200, [
            'Content-Type' => 'application/json;charset=UTF-8',
            'Charset' => 'utf-8'
        ], JSON_UNESCAPED_UNICODE);
    }




    protected static function is_multi_dimensional($array)
    {
        return count(array_filter($array, 'is_array')) > 0;
    }


    public static function convertArrayToSingle($array): array
    {
        if (!is_array($array)) {
            return [$array]; // Wrap non-array values in an array
        }

        $result = [];
        foreach ($array as $value) {
            if (is_array($value)) {
                // Recursively flatten the sub-array
                $result = array_merge($result, self::convertArrayToSingle($value));
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }


    //    protected static function showAll($message, Collection $collection, $code = 200)
    //    {
    //
    //        if ($collection->isEmpty()) {
    //            return self::successResponse($message, $collection, $code);
    //        }
    //
    //        return self::successResponse($message, $collection, $code);
    //    }
    //
    //    // protected static function showOne($message,   Model $model, $code = 200)
    //    protected static function showOne($message, $model, $code = 200)
    //    {
    //        return self::successResponse($message, $model, $code);
    //    }


    //
}
