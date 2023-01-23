<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Models\Event;
use Classiebit\Eventmie\Models\Booking;
use Classiebit\Eventmie\Models\Transaction;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
//Tinypesa Callback
Route::match(['get', 'post'], '/tinypesa/payment/callback', function (Request $request) {
    if (isset($request['Body']['stkCallback']['ResultCode'])) {
        $result_code = $request['Body']['stkCallback']['ResultCode'];
        $transaction_id = $request['Body']['stkCallback']['TinyPesaID'];

        $transaction = Transaction::where('txn_id', $transaction_id)->first();

        if ($transaction) {
            if ($result_code == 0) {
                $transaction->status = true;
                $transaction->payment_status = 'SUCCESS';
                $transaction->save();

                $booking = Booking::where('transaction_id', $transaction->id)->first();

                if ($booking) {
                    $booking->is_paid = true;
                    $booking->save();
                }
                $request = Request::create(route('eventmie.send_email'), 'GET');

                $response = app()->handle($request);

                logger(['Booking' => session('booking_email_data')]);

                logger(['Get response' => $response]);

                return response()->json(['success' => true, 'message' => 'Payment Processed!'], 200);
            } else {
                $transaction->status = false;
                $transaction->payment_status = 'FAILED';
                $transaction->save();

                $booking = Booking::where('transaction_id', $transaction->id)->first();

                if ($booking) {
                    $booking->delete();
                }
                return response()->json(['success' => false, 'message' => 'Cannot process payment!'], 400);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Transaction not found!'], 404);
        }
    } else {
        return response()->json(['success' => false, 'message' => 'Invalid payload'], 422);
    }
})->name('tinypesa_callback');
//Tinypesa Callback

Route::post('/registers', [UserController::class, 'register']);

Route::post('/login', [UserController::class, 'login']);


Route::post('/checkout', function (Request $request) {


    return response()->json(['status' => true, 'data' => json_decode($request->data)]);
});

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('get/events', function () {
        $events = Event::with('tickets')->paginate(5);

        return response()->json(['status' => true, 'events' => $events]);
    });



    Route::get('user', function (Request $request) {
        return json_decode($request->user());
    });

    // Route::post('/checkout', function(Request $request){

    //     return response()->json(['status' => true, 'data' => $request->all()]);
    // });

    Route::get('send/message', [MessagesController::class, 'sendMessage']);
    Route::get('get/messages', [MessagesController::class, 'getMessages']);
});