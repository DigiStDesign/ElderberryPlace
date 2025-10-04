

// Categories API client (robust to multiple backend route shapes)
// Usage:
// import Categories from '@/api/categories'
// Categories.list({ search: 'Allied' })
// Categories.show(1) // robust "get by id" supporting /categories/:id OR query styles
// Categories.create({ name: 'Physio', description: '...' })
// Categories.update(id, payload)
// Categories.remove(id)
// Categories.save(payload) // create or update depending on payload.id

import api from './client'

async function tryGet(url) {
  const res = await api.get(url);
  return res?.data;
}

function pickItemById(payload, id) {
  if (!payload) return null;

  // common API shapes: { data: {...} }, { data: [...] }, [...], or the item itself
  if (Array.isArray(payload)) {
    return payload.find(x => String(x.id ?? x.category_id) === String(id)) || null;
  }
  if (Array.isArray(payload.data)) {
    return payload.data.find(x => String(x.id ?? x.category_id) === String(id)) || null;
  }
  if (payload.data && (payload.data.id ?? payload.data.category_id)) {
    return payload.data;
  }
  if (payload.id ?? payload.category_id) {
    return payload;
  }
  return null;
}

const Categories = {
  // List with optional filters
  list(params = {}) {
    return api.get('/categories', { params });
  },

  /**
   * Robust "get by id".
   * Tries several common patterns before finally falling back to list() and filtering client-side.
   */
  async show(id) {
    const urls = [
      `/categories/${id}`,
      `/category/${id}`,
      `/categories?id=${id}`,
      `/category?id=${id}`,
    ];

    for (const url of urls) {
      try {
        const payload = await tryGet(url);
        const item = pickItemById(payload, id);
        if (item) return { data: item };
      } catch (e) {
        // Ignore 404/Not Found and continue trying other shapes.
        if (e?.response?.status !== 404) {
          // Bubble up other errors (403, 500, network, etc.)
          throw e;
        }
      }
    }

    // Final fallback: fetch all and pick client-side
    try {
      const listRes = await this.list();
      const listPayload = listRes?.data;
      const items = Array.isArray(listPayload)
        ? listPayload
        : Array.isArray(listPayload?.data)
          ? listPayload.data
          : Array.isArray(listPayload?.items)
            ? listPayload.items
            : [];
      const found = items.find(x => String(x.id ?? x.category_id) === String(id));
      if (found) return { data: found };
    } catch {
      /* ignore and throw generic below */
    }

    throw new Error('Category not found');
  },

  // Backwards-compatible alias
  get(id) {
    return this.show(id);
  },

  // Simple CRUD helpers (keep your current REST endpoints)
  create(data) {
    return api.post('/categories', data);
  },

  update(id, data) {
    return api.put(`/categories/${id}`, data);
  },

  remove(id) {
    // Prefer RESTful delete if available; otherwise support query style deletes in the caller.
    return api.delete(`/categories/${id}`);
  },

  // Convenience: create or update depending on presence of data.id
  save(data) {
    const id = data?.id ?? data?.category_id;
    return id ? this.update(id, data) : this.create(data);
  },
};

export default Categories;