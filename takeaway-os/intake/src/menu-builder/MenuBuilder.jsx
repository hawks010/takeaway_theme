import React, { useRef, useState } from 'react'
import {
  DndContext, KeyboardSensor, PointerSensor, useSensor, useSensors, closestCenter,
} from '@dnd-kit/core'
import {
  SortableContext, verticalListSortingStrategy, useSortable, sortableKeyboardCoordinates,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { api } from '../api'

const ALLERGENS = ['gluten', 'crustaceans', 'eggs', 'fish', 'peanuts', 'soy', 'milk', 'nuts', 'celery', 'mustard', 'sesame', 'sulphites', 'lupin', 'molluscs']
const DIETARY = ['vegetarian', 'vegan', 'halal', 'gluten_free']

function makeId() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') return crypto.randomUUID()
  return `ttos-${Math.random().toString(36).slice(2, 10)}`
}

function emptyItem() {
  return { id: makeId(), name: '', description: '', price: '', allergens: [], dietary: [], option_groups: [], kitchen_note: '', image_ref: '', image_name: '' }
}
function emptySub() {
  return { id: makeId(), name: '', image_ref: '', image_name: '', items: [] }
}
function emptyCategory() {
  return { id: makeId(), name: '', image_ref: '', image_name: '', sub_categories: [] }
}

function useAnnounce() {
  const ref = useRef(null)
  return {
    ref,
    say: (msg) => { if (ref.current) ref.current.textContent = msg },
  }
}

function PhotoPicker({ label, field, imageName, onUploaded }) {
  const [busy, setBusy] = useState(false)
  return (
    <label className="ttos-menu-photo">
      <span>{imageName ? `${label} — ${imageName} added` : `${label} (optional)`}</span>
      <input
        type="file"
        accept="image/jpeg,image/png,image/webp"
        disabled={busy}
        onChange={async (event) => {
          const [file] = Array.from(event.target.files || [])
          event.target.value = ''
          if (!file) return
          setBusy(true)
          try {
            const response = await api.uploadField(field, file)
            const list = response.uploads?.[field] || []
            const last = list[list.length - 1]
            if (last) onUploaded(last.stored_name, last.original_name || file.name)
          } finally {
            setBusy(false)
          }
        }}
      />
    </label>
  )
}

function DragHandle(props) {
  return (
    <button type="button" className="ttos-menu-handle" aria-label="Drag to reorder" {...props}>
      <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
        <circle cx="6" cy="4" r="1.4" fill="currentColor" /><circle cx="12" cy="4" r="1.4" fill="currentColor" />
        <circle cx="6" cy="9" r="1.4" fill="currentColor" /><circle cx="12" cy="9" r="1.4" fill="currentColor" />
        <circle cx="6" cy="14" r="1.4" fill="currentColor" /><circle cx="12" cy="14" r="1.4" fill="currentColor" />
      </svg>
    </button>
  )
}

function Sortable({ id, children, className }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id })
  return (
    <div ref={setNodeRef} className={className} style={{ transform: CSS.Transform.toString(transform), transition, opacity: isDragging ? 0.5 : 1 }}>
      <DragHandle {...attributes} {...listeners} />
      {children}
    </div>
  )
}

function useDnd(onReorder, say) {
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )
  const cancelTimer = useRef(null)
  const handleDragStart = (event, labelFor) => {
    say(`${labelFor(event.active.id)} picked up. Use arrow keys to move, space to drop, escape to cancel.`)
    clearTimeout(cancelTimer.current)
    cancelTimer.current = setTimeout(() => say('Reorder cancelled — no response.'), 15000)
  }
  const handleDragCancel = () => { clearTimeout(cancelTimer.current); say('Reorder cancelled.') }
  const handleDragEnd = (event, list, labelFor) => {
    clearTimeout(cancelTimer.current)
    const { active, over } = event
    if (!over || active.id === over.id) { say('Reorder cancelled.'); return }
    const oldIndex = list.findIndex((entry) => entry.id === active.id)
    const newIndex = list.findIndex((entry) => entry.id === over.id)
    if (oldIndex === -1 || newIndex === -1) return
    const next = [...list]
    const [moved] = next.splice(oldIndex, 1)
    next.splice(newIndex, 0, moved)
    onReorder(next)
    say(`${labelFor(active.id)} moved to position ${newIndex + 1}.`)
  }
  return { sensors, handleDragStart, handleDragCancel, handleDragEnd }
}

function ItemCard({ item, onChange, onRemove }) {
  const set = (patch) => onChange({ ...item, ...patch })
  const toggle = (key, value) => {
    const list = item[key] || []
    set({ [key]: list.includes(value) ? list.filter((v) => v !== value) : [...list, value] })
  }
  const addGroup = () => set({ option_groups: [...(item.option_groups || []), { name: '', type: 'single', required: false, min: 1, max: 1, options: [] }] })
  const updateGroup = (i, patch) => {
    const groups = [...(item.option_groups || [])]
    groups[i] = { ...groups[i], ...patch }
    if (patch.type) groups[i] = { ...groups[i], min: patch.type === 'single' ? 1 : 0, max: patch.type === 'single' ? 1 : Math.max(2, groups[i].options?.length || 2) }
    set({ option_groups: groups })
  }
  const removeGroup = (i) => set({ option_groups: (item.option_groups || []).filter((_, idx) => idx !== i) })
  const addChoice = (i) => {
    const groups = [...(item.option_groups || [])]
    groups[i] = { ...groups[i], options: [...(groups[i].options || []), { label: '', price: '0' }] }
    set({ option_groups: groups })
  }
  const updateChoice = (i, ci, patch) => {
    const groups = [...(item.option_groups || [])]
    const options = [...(groups[i].options || [])]
    options[ci] = { ...options[ci], ...patch }
    groups[i] = { ...groups[i], options }
    set({ option_groups: groups })
  }
  const removeChoice = (i, ci) => {
    const groups = [...(item.option_groups || [])]
    groups[i] = { ...groups[i], options: (groups[i].options || []).filter((_, idx) => idx !== ci) }
    set({ option_groups: groups })
  }

  return (
    <div className="ttos-menu-item">
      <div className="ttos-menu-item-top">
        <input className="ttos-menu-input" placeholder="Dish name" value={item.name} onChange={(e) => set({ name: e.target.value })} />
        <input className="ttos-menu-input ttos-menu-price" placeholder="Price" value={item.price} onChange={(e) => set({ price: e.target.value })} />
        <button type="button" className="ttos-menu-remove" onClick={onRemove} aria-label="Remove item">×</button>
      </div>
      <textarea className="ttos-menu-textarea" placeholder="A short, tasty description" value={item.description} onChange={(e) => set({ description: e.target.value })} />
      <PhotoPicker label="Dish photo" field="menu_item_photos" imageName={item.image_name} onUploaded={(ref, name) => set({ image_ref: ref, image_name: name })} />
      <div className="ttos-menu-chip-row">
        {ALLERGENS.map((a) => (
          <button key={a} type="button" className={`ttos-menu-chip${(item.allergens || []).includes(a) ? ' is-on' : ''}`} onClick={() => toggle('allergens', a)}>{a.replace('_', ' ')}</button>
        ))}
      </div>
      <div className="ttos-menu-chip-row">
        {DIETARY.map((d) => (
          <button key={d} type="button" className={`ttos-menu-chip is-dietary${(item.dietary || []).includes(d) ? ' is-on' : ''}`} onClick={() => toggle('dietary', d)}>{d.replace('_', ' ')}</button>
        ))}
      </div>
      {(item.option_groups || []).map((group, i) => (
        <div key={i} className="ttos-menu-group">
          <div className="ttos-menu-group-top">
            <input className="ttos-menu-input" placeholder="e.g. Size, Toppings" value={group.name} onChange={(e) => updateGroup(i, { name: e.target.value })} />
            <button type="button" className="ttos-menu-remove" onClick={() => removeGroup(i)} aria-label="Remove option group">×</button>
          </div>
          <div className="ttos-menu-toggle-row">
            <button type="button" className={`ttos-menu-toggle${group.type === 'single' ? ' is-on' : ''}`} onClick={() => updateGroup(i, { type: 'single' })}>Customer picks one</button>
            <button type="button" className={`ttos-menu-toggle${group.type === 'multiple' ? ' is-on' : ''}`} onClick={() => updateGroup(i, { type: 'multiple' })}>Customer can pick a few</button>
          </div>
          {(group.options || []).map((choice, ci) => (
            <div key={ci} className="ttos-menu-choice-row">
              <input className="ttos-menu-input" placeholder="Choice, e.g. Large" value={choice.label} onChange={(e) => updateChoice(i, ci, { label: e.target.value })} />
              <input className="ttos-menu-input ttos-menu-price" placeholder="+ price" value={choice.price} onChange={(e) => updateChoice(i, ci, { price: e.target.value })} />
              <button type="button" className="ttos-menu-remove" onClick={() => removeChoice(i, ci)} aria-label="Remove choice">×</button>
            </div>
          ))}
          <button type="button" className="ttos-menu-add-small" onClick={() => addChoice(i)}>+ Add a choice</button>
        </div>
      ))}
      <button type="button" className="ttos-menu-add-small" onClick={addGroup}>+ Add a choice group (e.g. size, extras)</button>
    </div>
  )
}

export default function MenuBuilder({ categories, onChange }) {
  const list = categories?.length ? categories : [
    { ...emptyCategory(), sub_categories: [{ ...emptySub(), items: [emptyItem()] }] },
  ]
  const announce = useAnnounce()
  const catDnd = useDnd(onChange, announce.say)

  const updateCategory = (index, patch) => onChange(list.map((c, i) => (i === index ? { ...c, ...patch } : c)))
  const removeCategory = (index) => onChange(list.filter((_, i) => i !== index))
  const addCategory = () => onChange([...list, emptyCategory()])

  const updateSub = (catIndex, subIndex, patch) => {
    onChange(list.map((c, i) => {
      if (i !== catIndex) return c
      const subs = c.sub_categories.map((s, si) => (si === subIndex ? { ...s, ...patch } : s))
      return { ...c, sub_categories: subs }
    }))
  }
  const removeSub = (catIndex, subIndex) => {
    onChange(list.map((c, i) => (i === catIndex ? { ...c, sub_categories: c.sub_categories.filter((_, si) => si !== subIndex) } : c)))
  }
  const addSub = (catIndex) => {
    onChange(list.map((c, i) => (i === catIndex ? { ...c, sub_categories: [...c.sub_categories, emptySub()] } : c)))
  }
  const reorderSubs = (catIndex, next) => {
    onChange(list.map((c, i) => (i === catIndex ? { ...c, sub_categories: next } : c)))
  }

  const updateItem = (catIndex, subIndex, itemIndex, patch) => {
    onChange(list.map((c, i) => {
      if (i !== catIndex) return c
      const subs = c.sub_categories.map((s, si) => {
        if (si !== subIndex) return s
        const items = s.items.map((it, ii) => (ii === itemIndex ? { ...it, ...patch } : it))
        return { ...s, items }
      })
      return { ...c, sub_categories: subs }
    }))
  }
  const removeItem = (catIndex, subIndex, itemIndex) => {
    onChange(list.map((c, i) => {
      if (i !== catIndex) return c
      const subs = c.sub_categories.map((s, si) => (si === subIndex ? { ...s, items: s.items.filter((_, ii) => ii !== itemIndex) } : s))
      return { ...c, sub_categories: subs }
    }))
  }
  const addItem = (catIndex, subIndex) => {
    onChange(list.map((c, i) => {
      if (i !== catIndex) return c
      const subs = c.sub_categories.map((s, si) => (si === subIndex ? { ...s, items: [...s.items, emptyItem()] } : s))
      return { ...c, sub_categories: subs }
    }))
  }
  const reorderItems = (catIndex, subIndex, next) => {
    onChange(list.map((c, i) => {
      if (i !== catIndex) return c
      const subs = c.sub_categories.map((s, si) => (si === subIndex ? { ...s, items: next } : s))
      return { ...c, sub_categories: subs }
    }))
  }

  return (
    <section className="ttos-intake-step ttos-menu-builder">
      <p className="ttos-intake-step-eyebrow">Menu builder</p>
      <h2>What's on the menu?</h2>
      <p>Drag things into the order you want. Add a photo to a category, a section, or a dish if it helps — totally optional.</p>
      <div aria-live="polite" ref={announce.ref} style={{ position: 'absolute', width: 1, height: 1, overflow: 'hidden' }} />

      <DndContext
        sensors={catDnd.sensors}
        collisionDetection={closestCenter}
        onDragStart={(e) => catDnd.handleDragStart(e, (id) => list.find((c) => c.id === id)?.name || 'Category')}
        onDragCancel={catDnd.handleDragCancel}
        onDragEnd={(e) => catDnd.handleDragEnd(e, list, (id) => list.find((c) => c.id === id)?.name || 'Category')}
      >
        <SortableContext items={list.map((c) => c.id)} strategy={verticalListSortingStrategy}>
          {list.map((category, catIndex) => (
            <Sortable key={category.id} id={category.id} className="ttos-menu-category">
              <div className="ttos-menu-category-body">
                <div className="ttos-menu-item-top">
                  <input className="ttos-menu-input ttos-menu-input-lg" placeholder="Category, e.g. Pizzas" value={category.name} onChange={(e) => updateCategory(catIndex, { name: e.target.value })} />
                  <button type="button" className="ttos-menu-remove" onClick={() => removeCategory(catIndex)} aria-label="Remove category">×</button>
                </div>
                <PhotoPicker label="Category photo" field="menu_category_photo" imageName={category.image_name} onUploaded={(ref, name) => updateCategory(catIndex, { image_ref: ref, image_name: name })} />

                <SubCategoryList
                  category={category}
                  catIndex={catIndex}
                  onReorder={(next) => reorderSubs(catIndex, next)}
                  onUpdateSub={(subIndex, patch) => updateSub(catIndex, subIndex, patch)}
                  onRemoveSub={(subIndex) => removeSub(catIndex, subIndex)}
                  onAddSub={() => addSub(catIndex)}
                  onUpdateItem={(subIndex, itemIndex, patch) => updateItem(catIndex, subIndex, itemIndex, patch)}
                  onRemoveItem={(subIndex, itemIndex) => removeItem(catIndex, subIndex, itemIndex)}
                  onAddItem={(subIndex) => addItem(catIndex, subIndex)}
                  onReorderItems={(subIndex, next) => reorderItems(catIndex, subIndex, next)}
                  announce={announce.say}
                />
              </div>
            </Sortable>
          ))}
        </SortableContext>
      </DndContext>

      <button type="button" className="ttos-intake-primary" onClick={addCategory}>+ Add a category</button>
    </section>
  )
}

function SubCategoryList({ category, onReorder, onUpdateSub, onRemoveSub, onAddSub, onUpdateItem, onRemoveItem, onAddItem, onReorderItems, announce }) {
  const subs = category.sub_categories || []
  const dnd = useDnd(onReorder, announce)

  return (
    <div className="ttos-menu-subs">
      <DndContext
        sensors={dnd.sensors}
        collisionDetection={closestCenter}
        onDragStart={(e) => dnd.handleDragStart(e, (id) => subs.find((s) => s.id === id)?.name || 'Section')}
        onDragCancel={dnd.handleDragCancel}
        onDragEnd={(e) => dnd.handleDragEnd(e, subs, (id) => subs.find((s) => s.id === id)?.name || 'Section')}
      >
        <SortableContext items={subs.map((s) => s.id)} strategy={verticalListSortingStrategy}>
          {subs.map((sub, subIndex) => (
            <Sortable key={sub.id} id={sub.id} className="ttos-menu-sub">
              <div className="ttos-menu-sub-body">
                <div className="ttos-menu-item-top">
                  <input className="ttos-menu-input" placeholder="Section name, e.g. Classics (optional)" value={sub.name} onChange={(e) => onUpdateSub(subIndex, { name: e.target.value })} />
                  <button type="button" className="ttos-menu-remove" onClick={() => onRemoveSub(subIndex)} aria-label="Remove section">×</button>
                </div>
                <PhotoPicker label="Section photo" field="menu_subcategory_photo" imageName={sub.image_name} onUploaded={(ref, name) => onUpdateSub(subIndex, { image_ref: ref, image_name: name })} />

                <ItemList
                  items={sub.items || []}
                  announce={announce}
                  onReorder={(next) => onReorderItems(subIndex, next)}
                  onUpdateItem={(itemIndex, patch) => onUpdateItem(subIndex, itemIndex, patch)}
                  onRemoveItem={(itemIndex) => onRemoveItem(subIndex, itemIndex)}
                />
                <button type="button" className="ttos-menu-add-small" onClick={() => onAddItem(subIndex)}>+ Add a dish</button>
              </div>
            </Sortable>
          ))}
        </SortableContext>
      </DndContext>
      <button type="button" className="ttos-menu-add-small" onClick={onAddSub}>+ Add a section (optional)</button>
    </div>
  )
}

function ItemList({ items, onReorder, onUpdateItem, onRemoveItem, announce }) {
  const dnd = useDnd(onReorder, announce)
  return (
    <div className="ttos-menu-items">
      <DndContext
        sensors={dnd.sensors}
        collisionDetection={closestCenter}
        onDragStart={(e) => dnd.handleDragStart(e, (id) => items.find((it) => it.id === id)?.name || 'Dish')}
        onDragCancel={dnd.handleDragCancel}
        onDragEnd={(e) => dnd.handleDragEnd(e, items, (id) => items.find((it) => it.id === id)?.name || 'Dish')}
      >
        <SortableContext items={items.map((it) => it.id)} strategy={verticalListSortingStrategy}>
          {items.map((item, itemIndex) => (
            <Sortable key={item.id} id={item.id} className="ttos-menu-item-wrap">
              <ItemCard item={item} onChange={(patch) => onUpdateItem(itemIndex, patch)} onRemove={() => onRemoveItem(itemIndex)} />
            </Sortable>
          ))}
        </SortableContext>
      </DndContext>
    </div>
  )
}
