<?php
/**
 * SafeRoad AI - Dhaka Division road/corridor registry.
 *
 * These are presentation/analytics anchor corridors, not a replacement for a
 * full GIS road database. Named reports are matched to these aliases, while
 * reports on any other road are still grouped dynamically by road name.
 */
function srDhakaDivisionRoadRegistry(): array {
    return [
        [
            'id'=>'airport-road','road_name'=>'Airport Road','district'=>'Dhaka','start_label'=>'Airport End','end_label'=>'Banani End','baseline_accidents'=>9,
            'aliases'=>['Airport Road','Airport Rd','Dhaka Airport Road','Dhaka Airport Rd'],
            'display_start_point'=>[23.859142,90.401044],'points'=>[[23.8645,90.4005],[23.8518,90.4084],[23.8388,90.4024],[23.8217,90.3955]]
        ],
        [
            'id'=>'mirpur-road','road_name'=>'Mirpur Road','district'=>'Dhaka','start_label'=>'Technical End','end_label'=>'Dhanmondi End','baseline_accidents'=>7,
            'aliases'=>['Mirpur Road','Mirpur Rd'],
            'display_start_point'=>[23.782010,90.350650],'points'=>[[23.7975,90.3530],[23.7755,90.3654],[23.7545,90.3731]]
        ],
        [
            'id'=>'pragati-sarani','road_name'=>'Pragati Sarani','district'=>'Dhaka','start_label'=>'Kuril End','end_label'=>'Rampura End','baseline_accidents'=>5,
            'aliases'=>['Pragati Sarani','Progati Sarani','Pragati Shoroni','Progoti Sarani'],
            'display_start_point'=>[23.818140,90.422130],'points'=>[[23.8153,90.4217],[23.7986,90.4237],[23.7808,90.4256]]
        ],
        [
            'id'=>'kazi-nazrul-avenue','road_name'=>'Kazi Nazrul Islam Avenue','district'=>'Dhaka','start_label'=>'Farmgate End','end_label'=>'Shahbag End','baseline_accidents'=>4,
            'aliases'=>['Kazi Nazrul Islam Avenue','Kazi Nazrul Avenue','Kazi Nazrul Islam Ave','Kazi Nazrul Ave'],
            'display_start_point'=>[23.758150,90.389650],'points'=>[[23.7648,90.3892],[23.7496,90.3922],[23.7368,90.3952]]
        ],
        [
            'id'=>'jatrabari-gulistan','road_name'=>'Jatrabari-Gulistan Road','district'=>'Dhaka','start_label'=>'Gulistan End','end_label'=>'Jatrabari End','baseline_accidents'=>8,
            'aliases'=>['Jatrabari-Gulistan','Jatrabari Gulistan','Gulistan Jatrabari','Jatrabari Gulistan Road','Gulistan Jatrabari Road'],
            'display_start_point'=>[23.722790,90.413920],'points'=>[[23.7258,90.4170],[23.7137,90.4210],[23.7006,90.4278]]
        ],
        [
            'id'=>'dhaka-aricha-savar','road_name'=>'Dhaka-Aricha Highway (Savar)','district'=>'Dhaka','start_label'=>'Savar Bus Stand','end_label'=>'Nabinagar Side','baseline_accidents'=>6,
            'aliases'=>['Dhaka Aricha Highway','Dhaka-Aricha Highway','Savar Highway','Savar Bus Stand Road'],
            'display_start_point'=>[23.846170,90.252020],'points'=>[[23.8390,90.2660],[23.8462,90.2520],[23.8585,90.2315]]
        ],
        [
            'id'=>'tongi-gazipur-road','road_name'=>'Tongi-Gazipur Road','district'=>'Gazipur','start_label'=>'Tongi End','end_label'=>'Gazipur Chowrasta','baseline_accidents'=>10,
            'aliases'=>['Tongi Gazipur Road','Tongi-Gazipur Road','Gazipur Tongi Road','Dhaka Mymensingh Road Gazipur'],
            'display_start_point'=>[23.999610,90.420330],'points'=>[[23.9450,90.4070],[23.9750,90.4140],[23.9996,90.4203]]
        ],
        [
            'id'=>'nabinagar-chandra','road_name'=>'Nabinagar-Chandra Highway','district'=>'Gazipur','start_label'=>'Nabinagar','end_label'=>'Chandra','baseline_accidents'=>7,
            'aliases'=>['Nabinagar Chandra Highway','Nabinagar-Chandra Highway','Nabinagar Chandra Road','Chandra Nabinagar Road'],
            'display_start_point'=>[23.941100,90.249800],'points'=>[[23.9100,90.2700],[23.9411,90.2498],[24.0050,90.2360]]
        ],
        [
            'id'=>'narayanganj-link-road','road_name'=>'Narayanganj Link Road','district'=>'Narayanganj','start_label'=>'Signboard End','end_label'=>'Chashara End','baseline_accidents'=>8,
            'aliases'=>['Narayanganj Link Road','Narayanganj Link Rd','Signboard Chashara Road','Chashara Link Road'],
            'display_start_point'=>[23.680720,90.497730],'points'=>[[23.6950,90.5000],[23.6600,90.4990],[23.6238,90.5000]]
        ],
        [
            'id'=>'dhaka-chattogram-signboard','road_name'=>'Dhaka-Chattogram Highway (Signboard)','district'=>'Narayanganj','start_label'=>'Signboard','end_label'=>'Kanchpur Side','baseline_accidents'=>9,
            'aliases'=>['Dhaka Chattogram Highway','Dhaka-Chattogram Highway','Dhaka Chittagong Highway','Signboard Highway','Kanchpur Highway'],
            'display_start_point'=>[23.694200,90.495000],'points'=>[[23.7040,90.4800],[23.6942,90.4950],[23.7050,90.5220]]
        ],
        [
            'id'=>'dhaka-sylhet-narsingdi','road_name'=>'Dhaka-Sylhet Highway (Narsingdi)','district'=>'Narsingdi','start_label'=>'Narsingdi Entry','end_label'=>'Bhairab Side','baseline_accidents'=>6,
            'aliases'=>['Dhaka Sylhet Highway','Dhaka-Sylhet Highway','Narsingdi Highway','Narsingdi Dhaka Road'],
            'display_start_point'=>[23.919300,90.717600],'points'=>[[23.9000,90.6820],[23.9193,90.7176],[23.9440,90.7600]]
        ],
        [
            'id'=>'dhaka-tangail-highway','road_name'=>'Dhaka-Tangail Highway','district'=>'Tangail','start_label'=>'Tangail Bypass','end_label'=>'Elenga Side','baseline_accidents'=>8,
            'aliases'=>['Dhaka Tangail Highway','Dhaka-Tangail Highway','Tangail Highway','Tangail Bypass Road'],
            'display_start_point'=>[24.251300,89.916700],'points'=>[[24.2230,89.9450],[24.2513,89.9167],[24.3020,89.8710]]
        ],
        [
            'id'=>'dhaka-manikganj-highway','road_name'=>'Dhaka-Manikganj Highway','district'=>'Manikganj','start_label'=>'Manikganj Entry','end_label'=>'Paturia Side','baseline_accidents'=>5,
            'aliases'=>['Dhaka Manikganj Highway','Dhaka-Manikganj Highway','Manikganj Highway','Manikganj Dhaka Road'],
            'display_start_point'=>[23.861650,90.000400],'points'=>[[23.8700,90.0380],[23.8617,90.0004],[23.8550,89.9500]]
        ],
        [
            'id'=>'dhaka-mawa-expressway','road_name'=>'Dhaka-Mawa Expressway','district'=>'Munshiganj','start_label'=>'Sreenagar Side','end_label'=>'Mawa End','baseline_accidents'=>7,
            'aliases'=>['Dhaka Mawa Expressway','Dhaka-Mawa Expressway','Mawa Expressway','Dhaka Mawa Road'],
            'display_start_point'=>[23.566100,90.294500],'points'=>[[23.6100,90.3240],[23.5661,90.2945],[23.4700,90.2640]]
        ],
        [
            'id'=>'faridpur-dhaka-highway','road_name'=>'Faridpur-Dhaka Highway','district'=>'Faridpur','start_label'=>'Faridpur Entry','end_label'=>'Bhanga Side','baseline_accidents'=>6,
            'aliases'=>['Faridpur Dhaka Highway','Faridpur-Dhaka Highway','Dhaka Faridpur Highway','Dhaka-Faridpur Highway'],
            'display_start_point'=>[23.607100,89.842100],'points'=>[[23.6071,89.8421],[23.5520,89.9300],[23.4900,90.0200]]
        ],
        [
            'id'=>'kishoreganj-bhairab-road','road_name'=>'Kishoreganj-Bhairab Road','district'=>'Kishoreganj','start_label'=>'Bhairab Side','end_label'=>'Kishoreganj Side','baseline_accidents'=>4,
            'aliases'=>['Kishoreganj Bhairab Road','Kishoreganj-Bhairab Road','Bhairab Kishoreganj Road'],
            'display_start_point'=>[24.052000,90.984000],'points'=>[[24.0520,90.9840],[24.1500,90.9000],[24.3000,90.8400]]
        ],
        [
            'id'=>'rajbari-kushtia-highway','road_name'=>'Rajbari-Kushtia Highway','district'=>'Rajbari','start_label'=>'Rajbari Side','end_label'=>'Goalanda Side','baseline_accidents'=>5,
            'aliases'=>['Rajbari Kushtia Highway','Rajbari-Kushtia Highway','Rajbari Highway','Goalanda Rajbari Road'],
            'display_start_point'=>[23.757400,89.644500],'points'=>[[23.7574,89.6445],[23.7390,89.6800],[23.7250,89.7400]]
        ],
        [
            'id'=>'madaripur-shariatpur-road','road_name'=>'Madaripur-Shariatpur Road','district'=>'Madaripur','start_label'=>'Madaripur End','end_label'=>'Shariatpur Side','baseline_accidents'=>4,
            'aliases'=>['Madaripur Shariatpur Road','Madaripur-Shariatpur Road','Shariatpur Madaripur Road'],
            'display_start_point'=>[23.164900,90.189700],'points'=>[[23.1649,90.1897],[23.1900,90.2700],[23.2150,90.3500]]
        ],
        [
            'id'=>'shariatpur-mawa-road','road_name'=>'Shariatpur-Mawa Road','district'=>'Shariatpur','start_label'=>'Shariatpur End','end_label'=>'Mawa Side','baseline_accidents'=>5,
            'aliases'=>['Shariatpur Mawa Road','Shariatpur-Mawa Road','Mawa Shariatpur Road'],
            'display_start_point'=>[23.242200,90.434200],'points'=>[[23.2422,90.4342],[23.3200,90.3900],[23.4200,90.3100]]
        ],
        [
            'id'=>'gopalganj-tungipara-road','road_name'=>'Gopalganj-Tungipara Road','district'=>'Gopalganj','start_label'=>'Gopalganj End','end_label'=>'Tungipara End','baseline_accidents'=>3,
            'aliases'=>['Gopalganj Tungipara Road','Gopalganj-Tungipara Road','Tungipara Gopalganj Road'],
            'display_start_point'=>[23.005100,89.826600],'points'=>[[23.0051,89.8266],[22.9650,89.8500],[22.9100,89.8900]]
        ]
    ];
}
?>
