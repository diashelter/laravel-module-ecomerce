export type StaffRole = 'admin' | 'support'
export type ProductStatus = 'active' | 'inactive'
export type OrderStatus = 'placed' | 'awaiting_payment' | 'payment_approved' | 'delivered'

/** A shopper account (`customers`). It has no role. */
export interface Customer {
  id: number
  name: string
  email: string
  orders_count?: number
  orders?: Order[]
  created_at: string
}

/** A staff member (`users`) of the admin area. */
export interface StaffMember {
  id: number
  name: string
  email: string
  role: StaffRole
  role_label: string
  created_at: string
}

export interface Category {
  id: number
  name: string
  slug: string
  products_count?: number
}

export interface Product {
  id: number
  name: string
  price_cents: number
  description: string
  image_url: string | null
  status: ProductStatus
  status_label: string
  is_available: boolean
  available_quantity: number
  stock: { id: number; quantity: number } | null
  categories: Category[]
  created_at: string
  updated_at: string
}

export interface Stock {
  id: number
  quantity: number
  product: {
    id: number
    name: string
    image_url: string | null
    status: ProductStatus
    status_label: string
    is_available: boolean
  }
  updated_at: string
}

export interface OrderItem {
  id: number
  product_id: number
  product_name: string
  unit_price_cents: number
  quantity: number
  subtotal_cents: number
}

export interface TimelineStep {
  status: OrderStatus
  label: string
  step: number
  completed: boolean
}

export interface Order {
  id: number
  total_cents: number
  status: OrderStatus
  status_label: string
  status_step: number
  timeline: TimelineStep[]
  items_count?: number
  items?: OrderItem[]
  customer?: { id: number; name: string; email: string }
  created_at: string
  updated_at: string
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}

export interface CartItemPayload {
  product_id: number
  quantity: number
}

export interface CartValidationLine {
  product_id: number
  name: string | null
  image_url: string | null
  unit_price_cents: number | null
  quantity: number
  subtotal_cents: number | null
  available_quantity: number
  is_available: boolean
  problem: string | null
}

export interface CartValidation {
  items: CartValidationLine[]
  total_cents: number
  is_valid: boolean
}

export interface AccountSummary {
  customer: Customer
  orders_count: number
  last_order: Order | null
  recent_orders: Order[]
}

export interface ChartPoint {
  label: string
  total: number
}

export interface DashboardData {
  cards: {
    total_products: number
    active_products: number
    inactive_products: number
    total_stock_units: number
    total_customers: number
    total_orders: number
  }
  stock: {
    products_in_stock: number
    products_out_of_stock: number
  }
  orders_per_day: ChartPoint[]
  orders_per_month: ChartPoint[]
}

export type ValidationErrors = Record<string, string[]>
