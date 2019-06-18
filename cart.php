<?php

require_once(dirname(__FILE__)."/../init.php");
require_once(dirname(__FILE__)."/../../catalog/include/item.php");

es_include("localobject.php");
es_include("page.php");

class Cart extends LocalObject
{
	var $module;
	var $config;
	
	function Cart($module, $config = array(), $data = array())
	{
		parent::LocalObject($data);
		
		$this->module = $module;
		$this->config = is_array($config) ? $config : array();
	}
  
	function AddToCart($itemID, $pageID)
	{
		$session = GetSession();
		if(!empty($session->GetProperty("CartItemList")))
		{
			$itemList = $session->GetProperty("CartItemList");
			$isInCart = false;
			foreach($itemList as &$item)
			{
				if($item["ItemID"] == $itemID)
				{
					$item["Quantity"] = $item["Quantity"] + 1;
					$isInCart = true;
				}
			}
			
			if($isInCart == false)
			{
			    $itemList[] = array("ItemID" => $itemID, "Quantity" => 1, "PageID" => $pageID);
			}
			$session->SetProperty("CartItemList", $itemList);
			$session->SaveToDB();
		}
		else
		{
		    $itemList = array();
		    $itemList[] = array("ItemID" => $itemID, "Quantity" => 1, "PageID" => $pageID);
			$session->SetProperty("CartItemList", $itemList);
			$session->SaveToDB();
		}
	}

	function DeleteFromCart($itemID)
	{
		$session = GetSession();
		$itemList = $session->GetProperty("CartItemList");
		foreach($itemList as $key => $item)
		{
			if($item["ItemID"] == $itemID)
			{
				unset($itemList[$key]);
			}
		}
		$itemList = array_values($itemList);
		$session->SetProperty("CartItemList", $itemList);
		$session->SaveToDB();
	}
	
	function SetQuantity($itemID, $quantity)
	{
		$session = GetSession();
		$itemList = $session->GetProperty("CartItemList");
		$isInCart = false;
		foreach($itemList as &$item)
		{
			if($item["ItemID"] == $itemID)
			{
				$page = new Page();
				$page->LoadByID($item["PageID"]);
				$config = $page->GetConfig();
				
				$itemInfo = new CatalogItem("catalog", $item["PageID"], $config);
				$itemInfo->LoadByID($item["ItemID"]);
				$item["Quantity"] = $quantity;
				$cost = $itemInfo->GetProperty("Price") * $item["Quantity"];
				$saleCost = $itemInfo->GetProperty("SalePrice") * $item["Quantity"];
				$isInCart = true;		
			}
		}
		if($isInCart === true)
		{
    		$session->SetProperty("CartItemList", $itemList);
    		$session->SaveToDB();
    		$response = array("ItemID" => $itemID, "Quantity" => $quantity, "Cost" => $cost, "SaleCost" => $saleCost, "CostFormat" => number_format($cost, 0, '', ' ' ), "SaleCostFormat" => number_format($saleCost, 0, '', ' ' ));
    		return $response;
		}
		else 
		{
		    $response = array("ItemID" => false);
		    return $response;
		}
	}
	
	function GetCartItemList()
	{
		$session = GetSession();
		$itemList = $session->GetProperty("CartItemList");
		$resultItemList = array();

		if(!empty($itemList))
		{
		    es_include("citylist.php");
		    $cityList = new CityList();
		    $currentCityID = $cityList->GetCurrentCityID();
		    
    		foreach($itemList as $item)
    		{
    			$page = new Page();
    			$page->LoadByID($item["PageID"]);
    			$config = $page->GetConfig();

    			$itemInfo = new CatalogItem("catalog", $item["PageID"], $config);
    			$itemInfo->LoadByID($item["ItemID"]);
    			
    			$branchList = $itemInfo->GetBranchListByIDs($item["ItemID"], $currentCityID);
    			$itemInfo->SetProperty("BranchList", $branchList);
    			
    			$itemInfo->SetProperty("Quantity", $item["Quantity"]);
    			$itemInfo->SetProperty("PageID", $item["PageID"]);
    			$itemInfo->SetProperty("Cost", $itemInfo->GetProperty("Price") * $item["Quantity"]);
    			$itemInfo->SetProperty("CostFormat", number_format($itemInfo->GetProperty("Cost"), 0, '', ' ' ));
    			$itemInfo->SetProperty("SaleCost", $itemInfo->GetProperty("SalePrice") * $item["Quantity"]);
    			$itemInfo->SetProperty("SaleCostFormat", number_format($itemInfo->GetProperty("SaleCost"), 0, '', ' ' ));
    			$itemInfo->SetProperty("ItemURL", PROJECT_PATH.$page->GetProperty("StaticPath")."/".$config["ItemURLPrefix"].'/'.$itemInfo->GetProperty("StaticPath").HTML_EXTENSION);
    			$resultItemList[] = $itemInfo->GetProperties();
    		} 
    		return $resultItemList;
		}
		else 
		{
		    return $itemList;
		}
	}
	
	function GetCartState()
	{
		$session = GetSession();
		$itemList = array();
		$itemList = $session->GetProperty("CartItemList");
		$quantity = 0;
		$totalCost = 0;
		if (!empty($itemList))
		{
    		foreach($itemList as $item)
    		{
    			$page = new Page();
    			$page->LoadByID($item["PageID"]);
    			$config = $page->GetConfig();
    			
    			$itemInfo = new CatalogItem("catalog", $item["PageID"], $config);
    			$itemInfo->LoadByID($item["ItemID"]);
    			if($itemInfo->GetProperty("SalePrice") > 0)
    			    $price = $itemInfo->GetProperty("SalePrice");
    			else 
    			    $price = $itemInfo->GetProperty("Price");
    			$totalCost = $totalCost + $price * $item["Quantity"];
    			$itemInfo->SetProperty("Quantity", $item["Quantity"]);
    			$quantity = $quantity + $item["Quantity"];
    		}
		}
		if((($quantity % 10) > 4) || (($quantity % 10) == 0) || (($quantity > 10) && ($quantity < 20)))
		    $itemsWord = GetTranslation("cart-item-quantity-word-3", $this->module);
        elseif(($quantity % 10) == 1)
            $itemsWord = GetTranslation("cart-item-quantity-word-1", $this->module);
		elseif((($quantity % 10) > 1) && (($quantity % 10) < 5))
		    $itemsWord = GetTranslation("cart-item-quantity-word-2", $this->module);
		
		    $cartState = array("TotalQuantity" => $quantity, "TotalCost" => $totalCost, "ItemsWord" => $itemsWord, "TotalCostFormat" => number_format($totalCost, 0, '', ' ' ));
		return $cartState;
	}
	
	function ClearCart()
	{
		$session = GetSession();
		$session->RemoveProperty("CartItemList");
		$session->SaveToDB();
	}
	
	function ValidateOrder($request)
	{   
	    $availble = true;
	    $itemList = $this->GetCartItemList();
	    foreach($itemList as $key => $item)
	        if($item["BranchList"] == "")
	            $availble = false;
	        
	    $cartState = $this->GetCartState();
	    
	    if($cartState["TotalQuantity"] == 0)
	        $this->AddError("cart-empty", $this->module);
	    elseif($availble !== true)
	        $this->AddError("item-not-availible", $this->module);
	    
        if(!$request->ValidateNotEmpty("MailTo"))
            $this->AddError("mailto-empty", $this->module);
        
        if(!$request->ValidateEmail("Email") && $request->ValidateNotEmpty("Email"))
            $this->AddError("wrong-mail", $this->module);
        
	    if(!$request->ValidateNotEmpty("Name"))
		    $this->AddError("name-empty", $this->module);

		if(!$request->ValidateNotEmpty("Phone"))
			$this->AddError("phone-empty", $this->module);
						
		if(!$request->GetProperty("Delivery"))
		    $this->AddError("delivery-empty", $this->module);
				
		if(!$request->GetProperty("PayType"))
		    $this->AddError("paytype-empty", $this->module);
		
		if($this->HasErrors())
		{
		    $this->AddError("order-not-send", $this->module);
		    return false;
		}
		else
		{
			return true;
		}
	}
	
	function SendOrder($request, $tmplPrefix)
	{
	    $emailTemplate = new PopupPage($this->module, false);
	    $email = $emailTemplate->Load($tmplPrefix."email.html");
	    
	    $itemList = $this->GetCartItemList();
	    $email->SetLoop("ItemList", $itemList);
	    
	    $cartState = $this->GetCartState();
	    $email->LoadFromArray($cartState);
	    
	    $email->LoadFromObject($request);
	    
	    $result = SendMailFromAdmin($request->GetProperty("MailTo"), $this->config["Subject"], $emailTemplate->Grab($email));
	    if ($result === true)
	    {
	        $this->AddMessage("order-send", $this->module);
	        $this->ClearCart();
	        return true;
	    }
	    else
	    {
	        $this->AddError("order-not-send", $this->module);
	        return false;
	    }
	}
}
?>