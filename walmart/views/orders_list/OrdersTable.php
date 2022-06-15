<?php
declare( strict_types=1 );

namespace Walmart\views\orders_list;

class OrdersTable
{
    public static function getOrdersTable( array $data ) :string
    {
        $cell = '';
        $key  = 0;

        foreach ( $data as $item ) {
            $cell .= self::getTableCell( ++$key, $item );
        }

        return self::getCss() . self::getJs() . self::getTableHeader() . $cell . self::getTableFooter();
    }

    private static function getTableCell( int $key, array $item )
    {
        return '<div class="row">
<div class="cell" data-title="Num">' . $key . '</div>
<div class="cell" data-title="Image"><img src="' . $item['image'] . '"></div>
<div class="cell" data-title="Order id">' . $item['order_id'] . '</div>
<div class="cell" data-title="Order date">' . $item['order_date'] . '</div>
<div class="cell" data-title="Shop id">' . $item['shop_id'] . '</div>
<div class="cell" data-title="Asin">' . $item['asin'] . '</div>
<div class="cell" data-title="Sku">' . $item['sku'] . '</div>
<div class="cell" data-title="Upc">' . $item['upc'] . '</div>
<div class="cell" data-title="Title">' . $item['product_name'] . '</div>
<div class="cell" data-title="Description">' . $item['short_description'] . '</div>
<div class="cell" data-title="Price">' . $item['price'] . '</div>
<div class="cell" data-title="Brand">' . $item['brand'] . '</div>
<div class="cell" data-title="Shipping weight">' . $item['shipping_weight'] . '</div>
<div class="cell" data-title="Tax code">' . $item['tax_code'] . '</div>
<div class="cell" data-title="Category">' . $item['category'] . '</div>
<div class="cell" data-title="Subcategory">' . $item['subcategory'] . '</div>
<div class="cell" data-title="Gender">' . $item['gender'] . '</div>
<div class="cell" data-title="Color">' . $item['color'] . '</div>
<div class="cell" data-title="Size">' . $item['size'] . '</div>
</div>';
    }

    private static function getTableHeader()
    {
        return '<div class="container-table100">
<h2>Orders list</h2>
<div class="wrap-table100">
<div class="table">
<div class="row header">
<div class="cell">Num</div>
<div class="cell">Image</div>
<div class="cell">Order id</div>
<div class="cell">Order date</div>
<div class="cell">Shop</div>
<div class="cell">Asin</div>
<div class="cell">Sku</div>
<div class="cell">Upc</div>
<div class="cell">Title</div>
<div class="cell">Description</div>
<div class="cell">Price</div>
<div class="cell">Brand</div>
<div class="cell">Shipping weight</div>
<div class="cell">Tax code</div>
<div class="cell">Category</div>
<div class="cell">Subcategory</div>
<div class="cell">Gender</div>
<div class="cell">Color</div>
<div class="cell">Size</div>
</div>';
    }

    private static function getTableFooter()
    {
        return '</div></div></div></div>';
    }

    private static function getCss()
    {
        return '<style>@font-face {
    font-family: Poppins-Regular;
    src: url(https://colorlib.com/etc/tb/Table_Responsive_v2/fonts/poppins/Poppins-Regular.ttf)
}

@font-face {
    font-family: Poppins-Bold;
    src: url(https://colorlib.com/etc/tb/Table_Responsive_v2/fonts/poppins/Poppins-Bold.ttf)
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box
}

body,
html {
    height: 100%;
    font-family: sans-serif
}

a {
    margin: 0;
    transition: all .4s;
    -webkit-transition: all .4s;
    -o-transition: all .4s;
    -moz-transition: all .4s
}

a:focus {
    outline: none!important
}

a:hover {
    text-decoration: none
}

h1,
h2,
h3,
h4,
h5,
h6 {
    margin: 0
}

p {
    margin: 0
}

ul,
li {
    margin: 0;
    list-style-type: none
}

input {
    display: block;
    outline: none;
    border: none!important
}

textarea {
    display: block;
    outline: none
}

textarea:focus,
input:focus {
    border-color: transparent!important
}

button {
    outline: none!important;
    border: none;
    background: 0 0
}

button:hover {
    cursor: pointer
}

iframe {
    border: none!important
}

.limiter {
    width: 100%;
    margin: 0 auto
}

.container-table100 {
    width: 100%;
    min-height: 100vh;
    background: #c4d3f6;
    display: -webkit-box;
    display: -webkit-flex;
    display: -moz-box;
    display: -ms-flexbox;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    padding: 33px 30px
}

.wrap-table100 {
width: 100%;
    margin-bottom: 15px;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    -ms-overflow-style: -ms-autohiding-scrollbar;
    border: 1px solid #DDD;
}

.table {
    width: 100%;
    display: table;
    margin: 0
}

@media screen and (max-width:768px) {
    .table {
        display: block
    }
}

.row {
    display: table-row;
    background: #fff
}

.row.header {
    color: #fff;
    background: #6c7ae0
}

.row.header .cell {
white-space: nowrap;
}

@media screen and (max-width:768px) {
    .row {
        display: block
    }
    .row.header {
        padding: 0;
        height: 0
    }
    .row.header .cell {
        display: none
    }
    .row .cell:before {
        font-family: Poppins-Bold;
        font-size: 12px;
        color: gray;
        line-height: 1.2;
        text-transform: uppercase;
        font-weight: unset!important;
        margin-bottom: 13px;
        content: attr(data-title);
        min-width: 98px;
        display: block
    }
}

.cell {
    display: table-cell
}

@media screen and (max-width:768px) {
    .cell {
        display: block
    }
}

.row .cell {
    font-family: Poppins-Regular;
    font-size: 15px;
    color: #666;
    line-height: 1.2;
    font-weight: unset!important;
    padding-top: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f2f2f2
}

.row.header .cell {
    font-family: Poppins-Regular;
    font-size: 18px;
    color: #fff;
    line-height: 1.2;
    font-weight: unset!important;
    padding-top: 19px;
    padding-bottom: 19px
}

.row .cell{
width: 100%;
padding: 1em;
}

.table,
.row {
    width: 100%!important
}

.row:hover {
    background-color: #ececff;
    cursor: pointer
}
.row:active {
    background-color: #ececff;
    cursor: move;
}

.cell img {
width:100px;
}

@media(max-width:768px) {
    .row {
        border-bottom: 1px solid #f2f2f2;
        padding-bottom: 18px;
        padding-top: 30px;
        padding-right: 15px;
        margin: 0
    }
    .row .cell {
        border: none;
        padding-left: 30px;
        padding-top: 16px;
        padding-bottom: 16px
    }
    .row .cell:nth-child(1) {
        padding-left: 30px
    }
    .row .cell {
        font-family: Poppins-Regular;
        font-size: 18px;
        color: #555;
        line-height: 1.2;
        font-weight: unset!important
    }
    .table,
    .row,
    .cell {
        width: 100%!important
    }
    .cell img {
width:100%;
}
}

.wrap-table100 {
	overflow-y: auto;
	height: 600px;
}

.row.header .cell {
	position: sticky; 
	top: 0;
	background: #6c7ae0;
}
</style>';
    }

    private static function getJs()
    {
        return '
<script>
    window.onload = function () {
        var scr = $(".wrap-table100");
        scr.mousedown(function () {
            var startX = this.scrollLeft + event.pageX;
            var startY = this.scrollTop + event.pageY;
            scr.mousemove(function () {
                this.scrollLeft = startX - event.pageX;
                this.scrollTop = startY - event.pageY;
                return false;
            });
        });
        $(window).mouseup(function () {
            scr.off("mousemove"); 
        });
    }
</script>
';
    }
}