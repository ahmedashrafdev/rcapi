<?php

namespace App\Http\Controllers\admin;

use App\Document;
use App\DocumentProduct;
use App\Http\Controllers\Controller;
use App\Product;
use App\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    public function get()
    {
        dd('get');
    }

    public function create(Request $request)
    {
        $branch = $request->user()->branch_id ?  $request->user()->branch_id : $request->branch;

        $document = Document::create([
            "created_by" => $request->user()->id,
            "account_id" => $request->account,
            "branch_to" => $request->branch_to,
            "type" => $request->type,
            "branch_id" => $branch
        ]);
        return response()->json($document);
    }


    public function close($id)
    {
        $document = Document::find($id);
        $items = DB::select("SELECT dp.qty , dp.real_qty , dp.product_id FROM document_product dp WHERE document_id = ?" , [$id]);
        foreach($items as $item){
            //check if document type is sell or buy return 
            if($document->type > 2){
                $qty = $item->real_qty  - $item->qty ;
                //check if document type is buy or sell return 
            }else if($document->type > 4){
                $qty = $item->real_qty  + $item->qty ;

                //check if document type is inventory or define or first balance
            } else if($document->type > 6){
                $qty = $item->qty;
                //chec if document type is transaction
            } else {
                $qty =  $item->real_qty + $item->qty;
                $toRec = [
                    "product_id" => $item->product_id,
                    "branch_id" => $document->branch_to,
                    "qty" => $item->real_qty_to + $item->qty,
                ];
                Stock::create($toRec);

            }
            $rec = [
                "product_id" => $item->product_id,
                "branch_id" => $document->branch_id,
                "qty" => $qty,
            ];
            Stock::create($rec);
        }
        $document->closed_at = now();
        $document->save();
        return response()->json("stock updated successfully");
    }
    public function findItems(Request $request)
    {
        $offset =   $request->show * ($request->page - 1);
        $items = DB::select("SELECT 
                            SQL_CALC_FOUND_ROWS dp.id , p.title , p.isbn , dp.qty , dp.real_qty
                            FROM document_product dp 
                            JOIN products p 
                                ON dp.product_id = p.id 
                            WHERE document_id = ? 
                            LIMIT ?
                            OFFSET ?" , [$request->doc , $request->show , $offset]);
        $count = DB::select("SELECT FOUND_ROWS() total")[0]->total;
        // dd($count);

        return response()->json(['items' => $items , 'total' => $count]);
    }

    public function attachAccount($id , Request $request)
    {
        $document = Document::find($id);
        $document->account_id = $request->account;
        $document->save();
        return response()->json($document);
    }

    public function detachAccount($id)
    {
        $document = Document::find($id);
        $document->account_id = null;
        $document->save();
        return response()->json($document);
    }


    public function attachDiscount($id , Request $request)
    {
        $document = Document::find($id);
        if($request->percent){
            $document->discount_percent = $request->percent;
            $document->save();
            return response()->json($document);
        } 
        $document->discount_value = $request->value;
        $document->save();
        return response()->json($document);
    }

    public function detachDiscount($id)
    {
        $document = Document::find($id);
        $document->discount_percent = null;
        $document->discount_value = null;
        $document->save();
        return response()->json($document);
    }


    public function insertDocItem(Request $request)
    {
        $product = Product::where("isbn" , $request->product)->first();
        // dd($product);
        $document = Document::find($request->doc);
        $qty = getItemStock($product->id , $document->branch_id);
        $qtyTo =  $document->branch_to ? getItemStock($product->id , $document->branch_to) : null;
        $item = DocumentProduct::where('product_id' , $product->id)->where('document_id' , $document->id)->first();
        if($item == null){
            $rec = [
                "product_id" => $product->id,
                "document_id" => $document->id,
                "qty" => $request->qty,
                "real_qty" => $qty,
                "real_qty_to" => $qtyTo,
            ];
            DocumentProduct::insert([$rec]);
        }else {
            $item->qty = $item->qty + $request->qty;
            $item->save();
        }
        $item = [
            "isbn" => $product->isbn,
            "title" => $product->title,
            "price" => $product->price,
        ];
        return response()->json($item);
    }

    public function updateQty($id , Request $request)
    {
        $record = DocumentProduct::find($id);
        $record->qty = $request->qty;
        $record->save();
        return response()->json($record->inventory_id);
    }
}