<?php

namespace App\Enum\Common\Module;

enum AppModuleEnum: int
{
    //
    // User & Access (100–199)
    case USERS               = 101; // Login, OTP, KYC, roles, permissions

        // Catalog & Supply (200–299)
    case CATEGORIES          = 201;
    case PRODUCTS            = 202;
    case INVENTORY           = 203;
    case LISTINGS            = 204; // Farmer crop listings
    case PRICING_BIDDING     = 205; // Fixed price / auction / negotiation

        // Orders & Transactions (300–399)
    case CARTS               = 301;
    case ORDERS              = 302;
    case ORDER_ITEMS         = 303;
    case PAYMENTS            = 304;
    case WALLETS             = 305;
    case SETTLEMENTS         = 306;
    case REFUNDS             = 307;
    case COMMISSIONS         = 308;

        // Logistics & Delivery (400–499)
    case DRIVERS             = 401;
    case VEHICLES            = 402;
    case SHIPMENTS           = 403;
    case ROUTES              = 404;
    case TRACKING            = 405;

        // Engagement & Trust (500–599)
    case NOTIFICATIONS       = 501;
    case REVIEWS             = 502;
    case RATINGS             = 503;
    case DISPUTES            = 504;

        // Reports & Analytics (600–699)
    case REPORTS             = 601;
    case ANALYTICS           = 602;
    case DASHBOARDS          = 603;

        // System & Configuration (700–799)
    case MASTERS             = 701;
    case SETTINGS            = 702;
    case CMS                 = 703;
    case LEGALS              = 704;

        // Admin & Operations (800–899)
    case ADMIN               = 801;
    case AUDIT_LOGS          = 802;
}
