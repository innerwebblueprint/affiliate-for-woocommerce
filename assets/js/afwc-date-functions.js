/* phpcs:ignoreFile */
const afwcDateFunctions = {
    getDate: function(dateValue = '') {
        if(!dateValue){
            return '';
        }
        const now = new Date()
        let startDate, endDate = ''

        switch (dateValue) {
            case 'today':
                startDate = now
                endDate = now
                break
            case 'yesterday':
                startDate = this.subtractDays(now, 1)
                endDate = startDate
                break
            case 'this_week':
                startDate = this.subtractDays(now, now.getDay() - 1)
                endDate = now
                break
            case 'last_week':
                startDate = new Date(now.getFullYear(), now.getMonth(), (now.getDate() - (now.getDay() - 1) - 7))
                endDate = this.subtractDays(now, now.getDay())
                break
            case 'last_4_weeks':
                startDate = this.subtractDays(now, 29)
                endDate = now
                break
            case 'this_month':
                startDate = new Date(now.getFullYear(), now.getMonth(), 1)
                endDate = now
                break
            case 'last_month':
                startDate = new Date(now.getFullYear(), now.getMonth() - 1, 1)
                endDate = new Date(now.getFullYear(), now.getMonth(), 0)
                break
            case '3_months':
                startDate = new Date(now.getFullYear(), now.getMonth()-2, 1)
                endDate = now
                break
            case '6_months':
                startDate = new Date(now.getFullYear(), now.getMonth()-5, 1)
                endDate = now
                break
            case 'this_year':
                startDate = new Date(now.getFullYear(), 0, 1)
                endDate = now
                break
            case 'last_year':
                startDate = new Date(now.getFullYear() - 1, 0, 1)
                endDate = new Date(now.getFullYear(), 0, 0)
                break
            default:
                startDate = new Date(now.getFullYear(), now.getMonth(), 1)
                endDate = now
                break
        }

        const tzoffset = (new Date()).getTimezoneOffset() * 60000
        startDate = new Date(startDate.getTime() - tzoffset)
        endDate = new Date(endDate.getTime() - tzoffset)
        return {'startDate': this.formatISODate(startDate), 'endDate': this.formatISODate(endDate)}
    },

    getFullDate: function(day = 0)  {
        const dateObj = this.subtractDays(new Date(), -day)
        return this.formatDate(dateObj)
    },

    isValidDate: function(dateStr = '', separator = '_') {
        if(!dateStr){
            return false
        }
        if (Array.isArray(dateStr)) {
            return dateStr.every(date => this.isValidDate(date, separator))
        }
        const bits = dateStr.split(separator)
        return (
            bits.length === 3 &&
            this.isValidDateParts(bits)
        )
    },

    getDateTime: (dateStr = '', {dayStart = false, dayEnd = false} = {}) => {
        const now = new Date()
        const hr = dayStart ? '00' : (dayEnd ? '23' : now.getHours())
        const min = dayStart ? '00' : (dayEnd ? '59' : now.getMinutes())
        const sec = dayStart ? '00' : (dayEnd ? '59' : now.getSeconds())
        return `${dateStr} ${hr}:${min}:${sec}`
    },

    isValidDateParts: (bits) => (
        !isNaN(bits[0]) && bits[0] >= 2000 && bits[0] <= 9999 &&
        !isNaN(bits[1]) && bits[1] >= 1 && bits[1] <= 12 &&
        !isNaN(bits[2]) && bits[2] >= 1 && bits[2] <= 31
    ),

    subtractDays: (date, days) => new Date(date.getFullYear(), date.getMonth(), date.getDate() - days),

    subtractMonths: (date, months) => new Date(date.getFullYear(), date.getMonth() - months, date.getDate()),

    formatISODate: (date) => date.toISOString().slice(0, 10),
    formatDate: (date) => `${date.getFullYear()}-${(`0${date.getMonth() + 1}`).slice(-2)}-${(`0${date.getDate()}`).slice(-2)}`
}