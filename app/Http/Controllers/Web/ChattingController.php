<?php

namespace App\Http\Controllers\Web;

use App\Events\ChattingEvent;
use App\Http\Controllers\Controller;
use App\Models\Chatting;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\ProductCompare;
use App\Models\Seller;
use App\Models\User;
use App\Models\Wishlist;
use App\Utils\ImageManager;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChattingController extends Controller
{
    public function __construct(
        private Order $order,
        private Wishlist $wishlist,
        private ProductCompare $compare,

    )
    {

    }
    public function chat_list(Request $request, $type)
    {

        if ($type == 'seller')
        {
            $last_chat = Chatting::with(['shop'])->where('user_id', auth('customer')->id())
                ->whereNotNull(['seller_id', 'user_id'])
                ->orderBy('created_at', 'DESC')
                ->first();
            if (isset($last_chat)) {
                // theme_aster - specific shop start
                if ($request->type =='seller'&& $request->has('id') ){
                    $last_chat = Chatting::with(['shop'])->where(['user_id'=> auth('customer')->id(),'shop_id'=>$request->id])
                            ->whereNotNull(['seller_id', 'user_id'])
                            ->orderBy('created_at', 'DESC')
                            ->first();
                        Chatting::with(['shop'])->where(['user_id'=> auth('customer')->id(),'shop_id'=>$request->id])
                            ->whereNotNull(['seller_id', 'user_id'])
                            ->orderBy('created_at', 'DESC')
                            ->update(['seen_by_customer' =>1]);
                }
                // theme_aster - specific shop end


                $chattings = Chatting::join('shops', 'shops.id', '=', 'chattings.shop_id')
                    ->select('chattings.*', 'shops.name', 'shops.image')
                    ->where('chattings.user_id', auth('customer')->id())
                    ->where('shop_id', $last_chat->shop_id)
                    ->when(theme_root_path()=='default' ,function($query){
                        return $query->orderBy('chattings.created_at', 'desc');
                    })
                    ->get();

                $unique_shops = Chatting::join('shops', 'shops.id', '=', 'chattings.shop_id')
                    ->join('sellers', 'sellers.id', '=', 'shops.seller_id')
                    ->select('chattings.*', 'shops.name', 'shops.image','shops.contact','sellers.email as seller_email')
                    ->where('chattings.user_id', auth('customer')->id())
                    ->when(theme_root_path()=='default' ,function($query){
                        return $query->orderBy('chattings.created_at', 'desc');
                    })
                    ->get()
                    ->unique('shop_id');

                    /*Unseen Message Count*/
                    $unique_shops?->map(function($unique_shop){
                        $unique_shop['unseen_message_count'] = Chatting::where([
                            'user_id' =>$unique_shop->user_id,
                            'seller_id'=>$unique_shop->seller_id,
                            'sent_by_customer'=>0,
                            'seen_by_customer'=>0,
                        ])->count();
                    });
                    /*End Unseen Message*/
                return view(VIEW_FILE_NAMES['user_inbox'], compact('chattings', 'unique_shops', 'last_chat'));
            }
        }elseif ($type == 'delivery-man')
        {
            $last_chat = Chatting::with('deliveryMan')->where('user_id', auth('customer')->id())
                ->whereNotNull(['delivery_man_id', 'user_id'])
                ->orderBy('created_at', 'DESC')
                ->first();
            if (isset($last_chat)) {
                // theme_aster - specific shop start
                if ($request->has('id')){
                    $last_chat = Chatting::with('deliveryMan')->where('delivery_man_id',$request->id)
                        ->orderBy('created_at', 'DESC')
                        ->first();
                    if ($last_chat) {
                        $last_chat->update(['seen_by_customer' =>1]);
                    }
                }// theme_aster - specific shop end

                $chattings = Chatting::join('delivery_men', 'delivery_men.id', '=', 'chattings.delivery_man_id')
                    ->select('chattings.*', 'delivery_men.f_name','delivery_men.l_name', 'delivery_men.image')
                    ->where('chattings.user_id', auth('customer')->id())
                    ->where('delivery_man_id', $last_chat->delivery_man_id)
                    ->when(theme_root_path()=='default' ,function($query){
                        return $query->orderBy('chattings.created_at', 'desc');
                    })
                    ->get();

                $unique_shops = Chatting::join('delivery_men', 'delivery_men.id', '=', 'chattings.delivery_man_id')
                    ->select('chattings.*', 'delivery_men.f_name','delivery_men.l_name', 'delivery_men.image','delivery_men.email')
                    ->where('chattings.user_id', auth('customer')->id())
                    ->orderBy('chattings.created_at', 'desc')
                    ->get()
                    ->unique('delivery_man_id');
                    /*Unseen Message Count*/
                    $unique_shops?->map(function($unique_shop){
                        $unique_shop['unseen_message_count'] = Chatting::where([
                                'user_id' =>$unique_shop->user_id,
                                'delivery_man_id'=>$unique_shop->delivery_man_id,
                                'sent_by_customer'=>0,
                                'seen_by_customer'=>0,
                            ])->count();
                    });
                    /*End Unseen Message*/
                return view(VIEW_FILE_NAMES['user_inbox'], compact('chattings', 'unique_shops', 'last_chat'));
            }
        }

        return view(VIEW_FILE_NAMES['user_inbox']);

    }
    public function messages(Request $request)
    {
        if ($request->has('shop_id'))
        {
            Chatting::where(['user_id'=>auth('customer')->id(), 'shop_id'=> $request->shop_id])->update([
                'seen_by_customer' => 1
            ]);

            $shops = Chatting::join('shops', 'shops.id', '=', 'chattings.shop_id')
                ->select('chattings.*', 'shops.name', 'shops.image')
                ->where('user_id', auth('customer')->id())
                ->where('chattings.shop_id', json_decode($request->shop_id))
                ->orderBy('created_at', 'ASC')
                ->get();
        }
        elseif ($request->has('delivery_man_id'))
        {
            Chatting::where(['user_id'=>auth('customer')->id(), 'delivery_man_id'=> $request->delivery_man_id])->update([
                'seen_by_customer' => 1
            ]);

            $shops = Chatting::join('delivery_men', 'delivery_men.id', '=', 'chattings.delivery_man_id')
                ->select('chattings.*',  'delivery_men.f_name','delivery_men.l_name', 'delivery_men.image')
                ->where('user_id', auth('customer')->id())
                ->where('chattings.delivery_man_id', json_decode($request->delivery_man_id))
                ->orderBy('created_at', 'ASC')
                ->get();
        }
        return response()->json($shops);


    }

    public function messages_store(Request $request)
    {
        $message_form = User::find(auth('customer')->id());
        if ($request->image == null && $request->message == '') {
            return response()->json(translate('type_something').'!', 403);
        }

        $image = [] ;
        if ($request->file('image')) {
            $validator = Validator::make($request->all(), [
                'image.*' => 'image|mimes:jpeg,png,jpg,gif|max:6000'
            ]);
            if ($validator->fails()) {
                return response()->json(translate('The_file_must_be_an_image').'!', 403);
            }
            foreach ($request->image as $key=>$value) {
                $image_name = ImageManager::upload('chatting/', 'webp', $value);
                $image[] = $image_name;
            }
        }

        if ($request->has('shop_id'))
        {
            $message = $request->message;
            Chatting::create([
                'user_id'          => auth('customer')->id(),
                'shop_id'          => $request->shop_id,
                'seller_id'        => $request->seller_id,
                'message'          => $request->message,
                'attachment'       => json_encode($image),
                'sent_by_customer' => 1,
                'seen_by_customer' => 1,
                'seen_by_seller'   => 0,
                'created_at'       => now(),
            ]);
            $seller = Seller::find($request->seller_id);
            ChattingEvent::dispatch('message_from_customer', 'seller', $seller, $message_form);

        }

        elseif ($request->has('delivery_man_id'))
        {
            $message = $request->message;
            Chatting::create([
                'user_id'          => auth('customer')->id(),
                'delivery_man_id'  => $request->delivery_man_id,
                'message'          => $request->message,
                'attachment'       => json_encode($image),
                'sent_by_customer' => 1,
                'seen_by_customer' => 1,
                'seen_by_delivery_man' => 0,
                'created_at'       => now(),
            ]);

            $delivery_man = DeliveryMan::find($request->delivery_man_id);
            ChattingEvent::dispatch('message_from_customer', 'delivery_man', $delivery_man, $message_form);
        }

        $imageArray = [];
        foreach ($image as $singleImage) {
            $imageArray[] = getValidImage(path: 'storage/app/public/chatting/'.$singleImage, type: 'product');
        }

        return response()->json(['message'=>$message,'image'=>$imageArray]);
    }

}
